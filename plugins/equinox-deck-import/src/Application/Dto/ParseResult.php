<?php

namespace AlteredCore\EquinoxDeckImport\Application\Dto;

/**
 * Outcome of the ParseUpload use case, serialized as the parse-zip JSON.
 */
final class ParseResult
{
    public bool $ok;
    public int $status;
    public ?string $error;
    /** @var array<int,array> each: name, format, hero, cards, matching_ids */
    public array $decks;
    public bool $dedupWarn;
    public bool $tokenPresent;
    /** @var array<string,mixed> */
    public array $debug;

    private function __construct()
    {
    }

    public static function failure(int $status, string $error): self
    {
        $r = new self();
        $r->ok = false;
        $r->status = $status;
        $r->error = $error;
        $r->decks = [];
        $r->dedupWarn = false;
        $r->tokenPresent = false;
        $r->debug = [];
        return $r;
    }

    /**
     * @param array<int,array>    $decks
     * @param array<string,mixed> $debug
     */
    public static function success(array $decks, bool $dedupWarn, bool $tokenPresent, array $debug): self
    {
        $r = new self();
        $r->ok = true;
        $r->status = 200;
        $r->error = null;
        $r->decks = $decks;
        $r->dedupWarn = $dedupWarn;
        $r->tokenPresent = $tokenPresent;
        $r->debug = $debug;
        return $r;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        if (!$this->ok) {
            return ['ok' => false, 'error' => $this->error];
        }
        return [
            'ok' => true,
            'decks' => $this->decks,
            'dedup_warn' => $this->dedupWarn,
            'token_present' => $this->tokenPresent,
            'debug' => $this->debug,
        ];
    }
}
