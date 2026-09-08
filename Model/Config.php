<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Typed access to every wb_aichatbot/* setting. The only place config paths are spelled out.
 */
class Config
{
    public const MODE_AUTO = 'auto';
    public const MODE_AI = 'ai';
    public const MODE_ASSIST = 'assist';

    private const PREFIX = 'wb_aichatbot/';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    public function isEnabled($storeId = null): bool
    {
        return $this->flag('general/enable', $storeId);
    }

    public function getMode($storeId = null): string
    {
        return $this->value('general/mode', $storeId) ?: self::MODE_AUTO;
    }

    public function isHideUntilSynced($storeId = null): bool
    {
        return $this->flag('general/hide_until_synced', $storeId);
    }

    /**
     * @return int[] empty = everyone
     */
    public function getCustomerGroups($storeId = null): array
    {
        $raw = (string)$this->value('general/customer_groups', $storeId);
        return $raw === '' ? [] : array_map('intval', explode(',', $raw));
    }

    public function getAssistantName($storeId = null): string
    {
        return (string)$this->value('assistant/name', $storeId);
    }

    public function getWelcomeMessage($storeId = null): string
    {
        return (string)$this->value('assistant/welcome_message', $storeId);
    }

    /**
     * @return string[]
     */
    public function getDefaultQuestions($storeId = null): array
    {
        return $this->lines((string)$this->value('assistant/default_questions', $storeId));
    }

    public function getPreferredLanguage($storeId = null): string
    {
        return (string)$this->value('assistant/preferred_language', $storeId) ?: 'en';
    }

    public function getBasePrompt($storeId = null): string
    {
        return (string)$this->value('assistant/base_prompt', $storeId);
    }

    public function getInstructions($storeId = null): string
    {
        return (string)$this->value('assistant/instructions', $storeId);
    }

    public function getTone($storeId = null): string
    {
        return (string)$this->value('assistant/tone', $storeId);
    }

    public function getMaxReplyTokens($storeId = null): int
    {
        return max(64, (int)$this->value('assistant/max_reply_tokens', $storeId));
    }

    public function getTemperature($storeId = null): float
    {
        return (float)$this->value('assistant/temperature', $storeId);
    }

    public function getMaxMessages($storeId = null): int
    {
        return (int)$this->value('assistant/max_messages', $storeId);
    }

    public function getMaxToolIterations($storeId = null): int
    {
        return max(1, (int)$this->value('assistant/max_tool_iterations', $storeId));
    }

    public function isAssistNoticeShown($storeId = null): bool
    {
        return $this->flag('assist/show_notice', $storeId);
    }

    public function getAssistNoticeText($storeId = null): string
    {
        return (string)$this->value('assist/notice_text', $storeId);
    }

    public function getRetryMinutes(): int
    {
        return max(1, (int)$this->value('assist/retry_minutes'));
    }

    public function getMaxProductCards($storeId = null): int
    {
        return max(1, (int)$this->value('assist/max_products', $storeId));
    }

    public function getWidgetPosition($storeId = null): string
    {
        return (string)$this->value('widget/position', $storeId) ?: 'bottom_right';
    }

    public function getPrimaryColor($storeId = null): string
    {
        return (string)$this->value('widget/primary_color', $storeId) ?: '#1b1f1d';
    }

    public function getLauncherTitle($storeId = null): string
    {
        return (string)$this->value('widget/launcher_title', $storeId);
    }

    public function getWindowTitle($storeId = null): string
    {
        return (string)$this->value('widget/window_title', $storeId);
    }

    public function getWindowSubtitle($storeId = null): string
    {
        return (string)$this->value('widget/window_subtitle', $storeId);
    }

    public function getAvatar($storeId = null): string
    {
        return (string)$this->value('widget/avatar', $storeId);
    }

    public function getInvitationMessage($storeId = null): string
    {
        return (string)$this->value('widget/invitation_message', $storeId);
    }

    public function getInvitationDelay($storeId = null): int
    {
        return (int)$this->value('widget/invitation_delay', $storeId);
    }

    public function getBottomOffsetMobile($storeId = null): int
    {
        return (int)$this->value('widget/bottom_offset_mobile', $storeId);
    }

    public function isSoundEnabled($storeId = null): bool
    {
        return $this->flag('widget/sound', $storeId);
    }

    public function isAttachmentsAllowed($storeId = null): bool
    {
        return $this->flag('widget/allow_attachments', $storeId);
    }

    public function isHumanHandoffAllowed($storeId = null): bool
    {
        return $this->flag('widget/allow_human_handoff', $storeId);
    }

    public function getLinkTarget($storeId = null): string
    {
        return (string)$this->value('widget/link_target', $storeId) ?: '_self';
    }

    /**
     * @return string[]
     */
    public function getExcludedPages($storeId = null): array
    {
        return $this->lines((string)$this->value('widget/excluded_pages', $storeId));
    }

    public function getCustomCss($storeId = null): string
    {
        return (string)$this->value('widget/custom_css', $storeId);
    }

    public function getMessagesPerMinute(): int
    {
        return max(1, (int)$this->value('limits/messages_per_minute'));
    }

    public function getConversationsPerDay(): int
    {
        return max(1, (int)$this->value('limits/conversations_per_day'));
    }

    public function getMaxMessageLength(): int
    {
        return max(50, (int)$this->value('limits/max_message_length'));
    }

    public function getCodesPerHourPerEmail(): int
    {
        return max(1, (int)$this->value('limits/codes_per_hour_email'));
    }

    public function getCodesPerHourPerIp(): int
    {
        return max(1, (int)$this->value('limits/codes_per_hour_ip'));
    }

    public function getCodeTtlMinutes(): int
    {
        return max(1, (int)$this->value('limits/code_ttl_minutes'));
    }

    public function getDailyTokenBudget(): int
    {
        return (int)$this->value('limits/daily_token_budget');
    }

    public function getRetentionDays(): int
    {
        return (int)$this->value('limits/retention_days');
    }

    public function isPiiRedacted(): bool
    {
        return $this->flag('limits/redact_pii');
    }

    public function getNotificationEmail(): string
    {
        $email = (string)$this->value('notifications/email');
        return $email !== '' ? $email : (string)$this->scopeConfig->getValue('contact/email/recipient_email');
    }

    public function isNotifyOnProviderFailure(): bool
    {
        return $this->flag('notifications/notify_on_provider_failure');
    }

    /**
     * Email template id (default or admin copy) for one of: verification, request_confirmation, request_admin,
     * request_reply, provider_failure.
     */
    public function getEmailTemplate(string $key, $storeId = null): string
    {
        $value = trim((string)$this->value('notifications/template_' . $key, $storeId));
        return $value !== '' ? $value : 'wb_aichatbot_notifications_template_' . $key;
    }

    public function isNotifyOnRequest(): bool
    {
        return $this->flag('notifications/notify_on_request');
    }

    public function isDebugLog(): bool
    {
        return $this->flag('developer/debug_log');
    }

    public function isMockAllowed(): bool
    {
        return $this->flag('developer/allow_mock');
    }

    /**
     * General > Store Information > Store Name (may be empty; callers fall back to the store view name).
     */
    public function getStoreName($storeId = null): string
    {
        return trim((string)$this->scopeConfig->getValue('general/store_information/name', ScopeInterface::SCOPE_STORE, $storeId));
    }

    private function value(string $path, $storeId = null)
    {
        return $this->scopeConfig->getValue(self::PREFIX . $path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    private function flag(string $path, $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::PREFIX . $path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @return string[]
     */
    private function lines(string $raw): array
    {
        $lines = array_map('trim', preg_split('/\r\n|\r|\n/', $raw) ?: []);
        return array_values(array_filter($lines, static function (string $line): bool {
            return $line !== '';
        }));
    }
}
