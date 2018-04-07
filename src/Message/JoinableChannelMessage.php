<?php

namespace App\Message;

use App\Channel\Channel;
use App\Exception\InvalidChannelException;
use App\Util\Util;
use CharlotteDunois\Yasmin\Models\GuildMember;
use CharlotteDunois\Yasmin\Models\PermissionOverwrite;
use CharlotteDunois\Yasmin\Models\TextChannel;
use Jikan\Model\Anime;

/**
 * Class JoinableChannelMessage
 * @package App\Message
 */
class JoinableChannelMessage
{
    public const CHANNEL_REGXP = '/(c=)(\d+)/';
    public const JOIN_REACTION = '▶';
    public const LEAVE_REACTION = '⏹';
    public const DELETE_REACTION = '🚮';
    public const RELOAD_REACTION = '🔁';
    public const TEXT_MESSAGE = '';

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
        return preg_match(self::CHANNEL_REGXP, $content);
    }

    /**
     * @param \CharlotteDunois\Yasmin\Models\Message $message
     * @return bool
     */
    public static function isJoinChannelMessage(\CharlotteDunois\Yasmin\Models\Message $message): bool
    {
        /** @noinspection CallableParameterUseCaseInTypeContextInspection */
        $message = new self($message);

        return $message->getFieldValue('kijkers') !== null;
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function getFieldValue(string $key)
    {
        if (!count($this->message->embeds)) {
            return null;
        }
        $data = array_filter(
            $this->message->embeds[0]->fields,
            function (array $data) use ($key) {
                return $data['name'] === $key;
            }
        );
        if (!count($data)) {
            return null;
        }
        $data = array_values($data);

        return $data[0]['value'];
    }

    /**
     * @return int|null
     */
    public function getAnimeId(): ?int
    {
        if (preg_match('#https?://myanimelist.net/anime/(\d+)#', $this->getAnimeLink(), $channel)) {
            return (int)$channel[1];
        }

        return null;
    }

    /**
     * @return string
     */
    public function getAnimeLink(): ?string
    {
        return $this->message->embeds[0]->url;
    }

    /**
     * @param GuildMember $member
     */
    public function addUser(GuildMember $member): void
    {
        // No double joins
        if ($this->hasAccess($member->id)) {
            return;
        }
        // Join channel
        $channel = $this->getChannelFromMessage();
        $channel->overwritePermissions(
            $member->id,
            Channel::ROLE_VIEW_MESSAGES,
            0,
            'User joined the channel'
        );
        // Update the member counf
        $count = $this->getSubsciberCount($channel) + 1;
        $this->updateWatchers($count);
        // Announce join
        $joinMessage = sprintf(
            ':inbox_tray:  %s kijkt nu mee naar %s',
            Util::mention((int)$member->id),
            Util::channelLink((int)$channel->id)
        );
        $channel->send($joinMessage);
    }

    /**
     * @param int $memberid
     * @return bool
     */
    public function hasAccess(int $memberid): bool
    {
        $permissions = $this->getChannelFromMessage()->permissionOverwrites->all();
        $view = array_filter(
            $permissions,
            function (PermissionOverwrite $o) use ($memberid) {
                return $o->allow->bitfield === Channel::ROLE_VIEW_MESSAGES
                    && $memberid === (int)$o->id
                    && $o->type === 'member';
            }
        );

        return count($view) > 0;
    }

    /**
     * @return TextChannel
     * @throws InvalidChannelException
     */
    public function getChannelFromMessage(): TextChannel
    {
        $channel = $this->message->guild->channels->get($this->getChannelId());
        if ($channel === null) {
            throw new InvalidChannelException('Channel not found');
        }

        return $channel;
    }

    /**
     * @return int|null
     */
    public function getChannelId(): ?int
    {
        if (preg_match(self::CHANNEL_REGXP, $this->getAnimeLink(), $channel)) {
            return (int)$channel[2];
        }

        return null;
    }

    /**
     * @param TextChannel $channel
     * @return int
     */
    public function getSubsciberCount(TextChannel $channel): int
    {
        return count($this->getSubscribers($channel)) - 1;
    }

    /**
     * @param TextChannel $channel
     * @return array
     */
    public function getSubscribers(TextChannel $channel): array
    {
        return array_filter(
            $channel->permissionOverwrites->all(),
            function (PermissionOverwrite $o) {
                return $o->allow->bitfield === Channel::ROLE_VIEW_MESSAGES;
            }
        );
    }

    /**
     * @param int $subs
     */
    public function updateWatchers(int $subs = 0): void
    {
        $anime = new Anime();
        $anime->title = $this->getAnimeTitle();
        $anime->episodes = $this->getFieldValue('afleveringen');
        $anime->aired_string = $this->getFieldValue('datum');
        $anime->image_url = $this->getAnimeImageUrl();
        preg_match('/c=(\d+)/', $this->getEmbeddedAnimeLink(), $channelid);
        $channelid = (int)$channelid[1];

        $embed = self::generateRichChannelMessage($anime, $channelid, $this->getEmbeddedAnimeLink(), $subs);
        $this->message->edit(self::TEXT_MESSAGE, $embed);
    }

    /**
     * @return string
     */
    public function getAnimeTitle(): string
    {
        return $this->message->embeds[0]->title ?? $this->message->embeds[0]->author['name'];
    }

    /**
     * @return string
     */
    public function getAnimeImageUrl(): string
    {
        return $this->message->embeds[0]->thumbnail['url'];
    }/** @noinspection MoreThanThreeArgumentsInspection */

    /**
     * @return string
     */
    public function getEmbeddedAnimeLink(): string
    {
        return $this->message->embeds[0]->url;
    }

    /**
     * @param Anime $anime
     * @param int $channelId
     * @param string $link
     * @param int $subs
     * @return array
     */
    public static function generateRichChannelMessage(Anime $anime, int $channelId, string $link, int $subs = 0): array
    {
        $authorImg = 'https://cdn.discordapp.com/icons/263798840996397056/a7c4da6b1413943fd4e220061fea55fd.png';

        return [
            'embed' => [
                'author'    => [
                    'name'     => $anime->title,
                    'icon_url' => $authorImg,
                    'url'      => $link,
                ],
                'url'       => $link,
                'thumbnail' => ['url' => $anime->image_url],
                'footer'    => [
                    'text' => 'Druk op de reactions om te joinen / leaven',
                ],
                'fields'    => [
                    [
                        'name'   => 'datum',
                        'value'  => $anime->aired_string,
                        'inline' => true,
                    ],
                    [
                        'name'   => 'afleveringen',
                        'value'  => $anime->episodes ?: '?',
                        'inline' => true,
                    ],
                    [
                        'name'   => 'channel',
                        'value'  => Util::channelLink($channelId),
                        'inline' => true,
                    ],
                    [
                        'name'   => 'kijkers',
                        'value'  => (string)$subs,
                        'inline' => true,
                    ],
                ],
            ],
        ];
    }

    /**
     * @param GuildMember $member
     */
    public function removeUser(GuildMember $member): void
    {
        // No double joins
        if (!$this->hasAccess($member->id)) {
            return;
        }
        // Remove member
        $channel = $this->getChannelFromMessage();
        $channel->overwritePermissions(
            $member->id,
            0,
            Channel::ROLE_VIEW_MESSAGES,
            'User left the channel'
        );
        // Update member count
        $count = $this->getSubsciberCount($channel) - 1;
        $this->updateWatchers($count);
        // Announce leave
        $channel->send(
            sprintf(
                ':outbox_tray: %s kijkt nu niet meer mee naar %s',
                Util::mention((int)$member->id),
                Util::channelLink($this->getChannelId())
            )
        );
    }

    /**
     * @return int
     */
    public function getWatchers(): int
    {
        return (int)$this->getFieldValue('kijkers');
    }
}
