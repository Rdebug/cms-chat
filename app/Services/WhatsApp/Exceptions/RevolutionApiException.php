<?php

namespace App\Services\WhatsApp\Exceptions;

use Exception;
use Illuminate\Http\Client\Response;

class RevolutionApiException extends Exception
{
    public static function missingConfiguration(): self
    {
        return new self('Revolution API configuration is missing. Please check your .env file.');
    }

    public static function apiError(string $message, Response $response): self
    {
        $details = $response->json();
        $errorMessage = $message . ': ' . json_encode($details);

        return new self($errorMessage, $response->status());
    }

    public static function sendFailed(Exception $previous): self
    {
        return new self('Failed to send message via Revolution API: ' . $previous->getMessage(), 0, $previous);
    }
}



