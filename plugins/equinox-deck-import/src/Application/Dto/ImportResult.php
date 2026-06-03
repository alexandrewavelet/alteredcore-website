<?php

namespace AlteredCore\EquinoxDeckImport\Application\Dto;

/**
 * Outcome of the ImportDeck use case, serialized as the import-deck JSON.
 *
 * `skip` and `imported` are successes (HTTP 200, ok=true). An API failure is
 * HTTP 200 with ok=false so the front-end queue can retry. Validation/token
 * failures are 4xx with ok=false.
 */
final class ImportResult
{
    public bool $ok;
    public int $status;            // HTTP status
    public string $resultStatus;   // 'skip' | 'imported' | 'error'
    public ?string $id;
    public ?string $errorCode;
    public ?string $errorMsg;
    /** @var array<string,mixed> */
    public array $debug;

    private function __construct()
    {
        $this->id = null;
        $this->errorCode = null;
        $this->errorMsg = null;
        $this->debug = [];
    }

    /**
     * @param array<string,mixed> $debug
     */
    public static function skipped(array $debug = []): self
    {
        $r = new self();
        $r->ok = true;
        $r->status = 200;
        $r->resultStatus = 'skip';
        $r->debug = $debug;
        return $r;
    }

    /**
     * @param array<string,mixed> $debug
     */
    public static function imported(?string $id, array $debug = []): self
    {
        $r = new self();
        $r->ok = true;
        $r->status = 200;
        $r->resultStatus = 'imported';
        $r->id = $id;
        $r->debug = $debug;
        return $r;
    }

    /**
     * API failure — HTTP 200, ok=false (front-end retries).
     *
     * @param array<string,mixed> $debug
     */
    public static function apiError(string $errorCode, string $errorMsg, array $debug = []): self
    {
        $r = new self();
        $r->ok = false;
        $r->status = 200;
        $r->resultStatus = 'error';
        $r->errorCode = $errorCode;
        $r->errorMsg = $errorMsg;
        $r->debug = $debug;
        return $r;
    }

    /**
     * Request/validation/token rejection — 4xx, ok=false, no debug.
     */
    public static function rejected(int $status, string $errorCode, string $errorMsg): self
    {
        $r = new self();
        $r->ok = false;
        $r->status = $status;
        $r->resultStatus = 'error';
        $r->errorCode = $errorCode;
        $r->errorMsg = $errorMsg;
        return $r;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        $out = ['ok' => $this->ok, 'status' => $this->resultStatus];
        if ($this->resultStatus === 'imported') {
            $out['id'] = $this->id;
        }
        if ($this->resultStatus === 'error') {
            $out['error_code'] = $this->errorCode;
            $out['error_msg'] = $this->errorMsg;
        }
        if (!empty($this->debug)) {
            $out['debug'] = $this->debug;
        }
        return $out;
    }
}
