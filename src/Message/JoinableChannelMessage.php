<?php

namespace App\Message;

/**
 * Class JoinableChannelMessage
 * @package App\Message
 */
class JoinableChannelMessage
{
    public const CHANNEL_REGXP = '/(c=)(\d+)/';
    public const ROLE_REGXP = '/(r=)(\d+)/';
    public const JOIN_REACTION = '▶';
    public const LEAVE_REACTION = '⏹';
    public const DELETE_REACTION = '🚮';

    /**
     * @var \CharlotteDunois\Yasmin\Models\Message
     */
    private $message;

    /**
     * JoinableChannelMessage constructor.
     * @param \CharlotteDunois\Yasmin\Models\Message $message
     */
    public function __construct(\CharlotteDunois\Yasmin\Models\Message $message)
    {
        $this->message = $message;
    }

    /**
     * @param string $content
     * @return bool
     */
    public static function isJoinableChannel(string $content): bool
    {
        if (!preg_match(self::CHANNEL_REGXP, $content)) {
            return false;
        }
        if (!preg_match(self::ROLE_REGXP, $content)) {
            return false;
        }

        return true;
    }

    /**
     * @return int|null
     */
    public function getRoleId(): ?int
    {
        if (preg_match(self::ROLE_REGXP, $this->message->content, $role)) {
            return (int)$role[2];
        }

        return null;
    }

    /**
     * @return int|null
     */
    public function getChannelId(): ?int
    {
        if (preg_match(self::CHANNEL_REGXP, $this->message->content, $channel)) {
            return (int)$channel[2];
        }

        return null;
    }
}
