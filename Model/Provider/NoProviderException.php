<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Provider;

/**
 * Nothing to call: no enabled, healthy credential exists (or the engine is forced into Assist mode).
 */
class NoProviderException extends \RuntimeException
{
}
