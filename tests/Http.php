<?php
declare(strict_types=1);

/** Cliente HTTP mínimo con jar de cookies, para pruebas funcionales contra el servidor local. */
final class Http
{
    private string $jar;
    public static string $base = 'http://localhost/historialmedico';

    public function __construct()
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'hme');
    }

    public function __destruct()
    {
        @unlink($this->jar);
    }

    /** @return array{status:int,body:string,location:?string,headers:array} */
    public function request(string $method, string $path, array $data = [], array $headers = []): array
    {
        $ch = curl_init(str_starts_with($path, 'http') ? $path : self::$base . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers,
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, self::flatten($data));
        }
        $raw = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $head = substr($raw, 0, $hs);
        $hdrs = [];
        foreach (explode("\r\n", $head) as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $hdrs[strtolower(trim($k))] = trim($v);
            }
        }
        return ['status' => $status, 'body' => substr($raw, $hs), 'location' => $hdrs['location'] ?? null, 'headers' => $hdrs];
    }

    /** curl no admite arreglos anidados: se convierten a campos «nombre[0]». */
    private static function flatten(array $data, string $prefix = ''): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            $key = $prefix === '' ? (string) $k : $prefix . '[' . $k . ']';
            if (is_array($v)) {
                $out += self::flatten($v, $key);
            } else {
                $out[$key] = $v;
            }
        }
        return $out;
    }

    public function get(string $path, array $headers = []): array
    {
        return $this->request('GET', $path, [], $headers);
    }

    /** GET que sigue redirecciones internas (máx. 5). */
    public function follow(string $path): array
    {
        $r = $this->get($path);
        for ($i = 0; $i < 5 && $r['location']; $i++) {
            $loc = $r['location'];
            $loc = str_starts_with($loc, 'http') ? $loc : 'http://localhost' . $loc;
            $r = $this->get($loc);
        }
        return $r;
    }

    public static function csrf(string $html): string
    {
        return preg_match('/name="_csrf" value="([a-f0-9]{64})"/', $html, $m) ? $m[1] : '';
    }

    /** Obtiene el formulario en $formPath (para el token CSRF) y envía POST a $postPath. */
    public function submit(string $formPath, string $postPath, array $data = [], array $files = []): array
    {
        $page = $this->follow($formPath);
        $token = self::csrf($page['body']);
        $fields = ['_csrf' => $token] + $data;
        foreach ($files as $name => [$path, $mime, $filename]) {
            $fields[$name] = new CURLFile($path, $mime, $filename);
        }
        return $this->request('POST', $postPath, $fields);
    }

    public function login(string $email, string $password): array
    {
        return $this->submit('/login', '/login', ['email' => $email, 'password' => $password]);
    }

    public function logout(): array
    {
        return $this->submit('/', '/logout');
    }
}
