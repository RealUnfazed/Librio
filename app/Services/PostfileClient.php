<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class PostfileClient
{
    private function http()
    {
        $key = config('library.postfile.key');
        if (! $key) {
            throw new RuntimeException('POSTFILE_API_KEY is not set.');
        }
        return Http::withHeaders(['X-API-Key' => $key])->acceptJson();
    }

    /** @return array{file_id:string,url:string,size:int,name:string} */
    public function upload(string $path, string $name): array
    {
        $res = $this->http()->timeout(900)
            ->attach('file', fopen($path, 'rb'), $name)
            ->post(rtrim(config('library.postfile.upload_url'), '/') . '/v1/upload');

        if ($res->failed() || ! $res->json('file_id') || ! $res->json('url')) {
            throw new RuntimeException('Postfile upload failed (HTTP ' . $res->status() . '): ' . mb_substr($res->body(), 0, 300));
        }
        return $res->json();
    }

    public function delete(string $fileId): void
    {
        $res = $this->http()->timeout(60)
            ->delete(rtrim(config('library.postfile.api_url'), '/') . '/v1/files/' . $fileId);

        if ($res->failed() && $res->status() !== 404) {
            throw new RuntimeException('Postfile delete failed (HTTP ' . $res->status() . ').');
        }
    }

    public function account(): array
    {
        $res = $this->http()->timeout(30)->get(rtrim(config('library.postfile.api_url'), '/') . '/v1/account');
        if ($res->failed()) {
            throw new RuntimeException('Postfile rejected the API key (HTTP ' . $res->status() . ').');
        }
        return $res->json();
    }
}
