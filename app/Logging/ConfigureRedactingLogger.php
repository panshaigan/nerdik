<?php

declare(strict_types=1);

namespace App\Logging;

use App\Support\Logging\RedactsSensitiveData;
use Illuminate\Log\Logger;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

final class ConfigureRedactingLogger
{
    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();

        $monolog->pushProcessor(new class implements ProcessorInterface
        {
            public function __invoke(LogRecord $record): LogRecord
            {
                return $record->with(
                    message: RedactsSensitiveData::redactString($record->message),
                    context: RedactsSensitiveData::redactArray($record->context),
                );
            }
        });
    }
}
