<?php

declare(strict_types=1);

namespace App\Services;

/** A rejected upload. The message is safe to show to an administrator or applicant. */
final class UploadException extends \RuntimeException
{
    public static function fromErrorCode(int $code): self
    {
        $message = match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file is too large.',
            UPLOAD_ERR_PARTIAL => 'The file was only partly uploaded. Please try again.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'The server has no temporary folder for uploads. Please contact the site administrator.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not save the file. Please contact the site administrator.',
            UPLOAD_ERR_EXTENSION => 'The upload was stopped by a server extension.',
            default => 'The file could not be uploaded.',
        };
        return new self($message);
    }
}
