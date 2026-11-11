<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Exception\ApiErrorCode;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * Parses the error payload shapes of the API: a single `{code,message,description}` object, a list of them,
 * or an envelope `{"errors": [...]}`. Codes are normalised (stray spaces removed).
 */
final class ErrorPayloadParser
{
    /**
     * Lenient: an unreadable body yields no error (used for HTTP error responses, which may be anything).
     */
    public function parseBody(string $body): ApiErrors
    {
        try {
            return $this->parsePayload(JsonDecoder::decode($body));
        } catch (UnexpectedUrssafResponse) {
            return ApiErrors::none();
        }
    }

    /**
     * @throws UnexpectedUrssafResponse when the structure is neither an object, a list of objects nor an envelope
     */
    public function parsePayload(Payload $payload): ApiErrors
    {
        if ($payload->isList()) {
            return $this->parseEntries($payload->entries());
        }

        if ($payload->string('code') !== null) {
            return $this->parseEntries([$payload]);
        }

        return $this->parseEntries($payload->items('errors'));
    }

    /**
     * Entries without a code are ignored.
     *
     * @param list<Payload> $entries
     */
    public function parseEntries(array $entries): ApiErrors
    {
        $errors = [];
        foreach ($entries as $entry) {
            $code = ApiErrorCode::normalise($entry->string('code') ?? '');
            if ($code === '') {
                continue;
            }

            $errors[] = ApiError::of($code, $entry->string('message') ?? '', $entry->string('description') ?? '');
        }

        return ApiErrors::of(...$errors);
    }
}
