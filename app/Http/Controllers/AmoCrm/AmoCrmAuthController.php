<?php

namespace App\Http\Controllers\AmoCrm;

use App\Http\Controllers\Controller;
use App\Models\Access;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class AmoCrmAuthController extends Controller {

    public function key(Request $request) {
        try {
            $response = Http::post('https://' . config('app.amo.domain') . '.amocrm.ru/oauth2/access_token', [
                'client_id' => config('app.amo.integration_id'),
                'client_secret' => config('app.amo.secret'),
                'grant_type' => 'authorization_code',
                'code' => $request->get('code'),
                'redirect_uri' => config('app.amo.redirect'),
            ]);

            $access = new Access();
            $access->__set('name', 'amo');
            $access->__set('access_token', $response['access_token']);
            $access->__set('refresh_token', $response['refresh_token']);
            $access->__set('expires_in', time() + $response['expires_in']);
            $access->save();
            return $access;

        } catch (\Exception $e) {
            return $e->getMessage() . " " . $e->getLine() . " " . $e->getFile();
        }
    }

    public function get() {
        $access = Access::where('name', 'amo')->first();
        if($access && $access['expires_in'] > time()) {
            return $access['access_token'];
        } else {
            return $this->update($access);
        }
    }

    protected function update(Access $access) {
        try {
            $response = Http::asForm()->post("https://" . config('app.amo.domain') . ".amocrm.ru/oauth2/access_token", [
                'client_id' => config('app.amo.integration_id'),
                'client_secret' => config('app.amo.secret'),
                'grant_type' => 'refresh_token',
                'refresh_token' => $access['refresh_token'],
                'redirect_uri' => config('app.amo.redirect'),
            ]);

            $access = Access::find($access['id']);
            $access->__set('access_token', $response['access_token']);
            $access->__set('refresh_token', $response['refresh_token']);
            $access->__set('expires_in', time() + $response['expires_in']);
            $access->save();

            return $response['access_token'];
        } catch (\Exception $e) {
            return $e->getMessage() . " " . $e->getLine() . " " . $e->getFile();
        }
    }

    public function get_request($path) {
        try {
            $access = $this->get();
            return Http::withHeaders([
                "Authorization" => "Bearer {$access}",
                "Content-Type" => "application/json",
            ])->get("https://" . config('app.amo.domain') . ".amocrm.ru/api/v4{$path}");
        } catch (\Exception $e) {
            return false;
        }
    }

    public function patch_request($path, $data) {
        try {
            $access = $this->get();
            return Http::withHeaders([
                "Authorization" => "Bearer {$access}",
                "Content-Type" => "application/json",
            ])->patch("https://" . config('app.amo.domain') . ".amocrm.ru/api/v4{$path}", $data);
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    public function post_request($path, $data) {
        try {
            $access = $this->get();
            return Http::withHeaders([
                "Authorization" => "Bearer {$access}",
                "Content-Type" => "application/json",
            ])->post("https://" . config('app.amo.domain') . ".amocrm.ru/api/v4{$path}", $data);
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    protected function pushData(Pool $pool, $path, $headers): \GuzzleHttp\Promise\PromiseInterface|\Illuminate\Http\Client\Response {
        return $pool->withHeaders($headers)->get($path);
    }

    public function get_request_pull(array $paths) {
        try {
            $access = $this->get();

            $headers = [
                "Authorization" => "Bearer {$access}",
                "Content-Type" => "application/json",
            ];
            $paths = collect($paths);
            return Http::pool(fn (Pool $pool) => $paths->map(fn ($path) => $this->pushData($pool, "https://" . config('app.amo.domain') . ".amocrm.ru/api/v4{$path}", $headers)));

        } catch (\Exception $e) {
            return false;
        }
    }
}
