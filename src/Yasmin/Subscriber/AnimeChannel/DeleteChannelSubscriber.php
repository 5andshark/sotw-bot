<?php

namespace App\Yasmin\Subscriber\AnimeChannel;

use App\Message\JoinableChannelMessage;
use App\Yasmin\Event\ReactionAddedEvent;
use CharlotteDunois\Yasmin\Models\Role;
use CharlotteDunois\Yasmin\Models\TextChannel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Delete a channel by Reaction
 * Class ValidateSubscriber
 * @package App\Yasmin\Subscriber
 */
class DeleteChannelSubscriber implements EventSubscriberInterface
{
    /**
     * @inheritdoc
     */
    public static function getSubscribedEvents(): array
    {
        return [ReactionAddedEvent::NAME => 'onCommand'];
    }

    /**
     * @param ReactionAddedEvent $event
     */
    public function onCommand(ReactionAddedEvent $event): void
    {
        $reaction = $event->getReaction();
        $io = $event->getIo();
        if (!$event->isAdmin()) {
            return;
        }
        if ($reaction->emoji->name !== JoinableChannelMessage::DELETE_REACTION) {
            return;
        }
        if (!JoinableChannelMessage::isJoinableChannel($reaction->message->content)) {
            $io->writeln('Not a joinable channel reaction');

            return;
        }
        $io->writeln(__CLASS__.' dispatched');
        $event->stopPropagation();

        // Load
        $channelMessage = new JoinableChannelMessage($reaction->message);
        /** @var TextChannel $channel */
        $channel = $reaction->message->guild->channels->get($channelMessage->getChannelId());
        $roleId = $channelMessage->getRoleId();

        // Delete
        /** @var Role $role */
        $role = $reaction->message->guild->roles->get($roleId);
        $role->delete('Remove joinable role');
        /** @var TextChannel $tmpChannel */
        $tmpChannel = $reaction->message->guild->channels->get($channelMessage->getChannelId());
        $tmpChannel->delete('Remove joinable channel');
        $io->success('Tmp channel removed #'.$channel->name);
        $reaction->message->delete();
    }
}
