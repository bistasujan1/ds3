<?php

declare(strict_types=1);

namespace Utils;

/**
 * Minimal cURL wrapper for outbound vendor APIs (Pioneer enterprise API, etc.).
 *
 * Every verb returns ['http_code' => int, 'response' => string|false, 'error' => ?string];
 * `error` is set when the call fails or returns a non-success HTTP code.
 */
class CurlHelper
{
    private string $url;

    /** @var array<string, string> */
    private array $headers = [];

    /** @var array<string, mixed>|string */
    private $data = [];

    private ?string $error = null;

    public function __construct(string $url)
    {
        $this->url = $url;
    }

    /**
     * @param scalar|null $value
     */
    public function setHeader(string $key, $value): void
    {
        $this->headers[$key] = (string) $value;
    }

    /**
     * @param mixed $value
     */
    public function setData(string $key, $value): void
    {
        if (!is_array($this->data)) {
            $this->data = [];
        }
        $this->data[$key] = $value;
    }

    /**
     * @param array<string, mixed>|string $data Form fields, or a pre-encoded body (e.g. JSON).
     */
    public function setDataArray($data): void
    {
        $this->data = $data;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * @return array{http_code:int, response:string|false, error:?string}
     */
    public function get(): array
    {
        return $this->send([CURLOPT_CUSTOMREQUEST => 'GET'], [200]);
    }

    /**
     * @return array{http_code:int, response:string|false, error:?string}
     */
    public function post(): array
    {
        return $this->send([CURLOPT_POST => true, CURLOPT_POSTFIELDS => $this->data], [200]);
    }

    /**
     * POST where 201 Created also counts as success.
     *
     * @return array{http_code:int, response:string|false, error:?string}
     */
    public function postAllowCreated(): array
    {
        return $this->send([CURLOPT_POST => true, CURLOPT_POSTFIELDS => $this->data], [200, 201]);
    }

    /**
     * @return array{http_code:int, response:string|false, error:?string}
     */
    public function put(): array
    {
        return $this->send([CURLOPT_POST => true, CURLOPT_POSTFIELDS => $this->data, CURLOPT_CUSTOMREQUEST => 'PUT'], [200]);
    }

    /**
     * @return array{http_code:int, response:string|false, error:?string}
     */
    public function patch(): array
    {
        return $this->send([CURLOPT_POST => true, CURLOPT_POSTFIELDS => $this->data, CURLOPT_CUSTOMREQUEST => 'PATCH'], [200, 204]);
    }

    /**
     * @param array<int, mixed> $options
     * @param list<int>         $successCodes
     * @return array{http_code:int, response:string|false, error:?string}
     */
    private function send(array $options, array $successCodes): array
    {
        $curl = curl_init();
        if ($curl === false) {
            $this->error = 'Unable to initialize cURL';
            return ['http_code' => 0, 'response' => false, 'error' => $this->error];
        }

        curl_setopt_array($curl, $options + [
            CURLOPT_URL => $this->url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => $this->buildHeaders(),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $response = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if (!in_array($httpCode, $successCodes, true)) {
            if (curl_errno($curl) > 0) {
                $this->error = curl_error($curl);
            } elseif ($httpCode > 0) {
                $this->error = 'HTTP error: ' . $httpCode;
            }
        }
        curl_close($curl);

        return ['http_code' => $httpCode, 'response' => $response, 'error' => $this->error];
    }

    /**
     * @return list<string>
     */
    private function buildHeaders(): array
    {
        $headers = [];
        foreach ($this->headers as $key => $value) {
            $headers[] = $key . ': ' . $value;
        }
        return $headers;
    }
}
