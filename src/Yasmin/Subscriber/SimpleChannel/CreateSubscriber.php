<?php

namespace App\Yasmin\Subscriber\SimpleChannel;

use App\Channel\SimpleChannelCreator;
use App\Context\CreateSimpleChannelContext;
use App\Yasmin\Event\MessageReceivedEvent;
use CharlotteDunois\Yasmin\Client;
use CharlotteDunois\Yasmin\Models\Message;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Lets admins run symfony commands
 * Class ValidateSubscriber
 * @package App\Yasmin\Subscriber
 */
class CreateSubscriber implements EventSubscriberInterface
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var Message
     */
    protected $message;

    /**
     * @var int
     */
    protected $everyoneRole;

    /**
     * @var SymfonyStyle
     */
    private $io;

    /**
     * @var SimpleChannelCreator
     */
    private $channelCreator;

    /**
     * CreateSubscriber constructor.
     * @param int $everyoneRole
     * @param SimpleChannelCreator $channelCreator
     */
    public function __construct(
        int $everyoneRole,
        SimpleChannelCreator $channelCreator
    ) {
        $this->everyoneRole = $everyoneRole;
        $this->channelCreator = $channelCreator;
    }

    /**
     * @inheritdoc
     */
    public static function getSubscribedEvents(): array
    {
        return [MessageReceivedEvent::NAME => 'onCommand'];
    }

    /**
     * @param MessageReceivedEvent $event
     */
    public function onCommand(MessageReceivedEvent $event): void
    {
        $this->message = $message = $event->getMessage();
        /** @var Client client */
        $matchCommand = preg_match('/^(\!haamc simplechannel )([\S]*)\s?(.*)$/', $message->content, $name);
        if (!$matchCommand || !$event->isAdmin()) {
            return;
        }
        $this->io = $io = $event->getIo();
        $io->writeln(__CLASS__.' dispatched');
        $event->stopPropagation();
        [$cmd, $cmd, $channelName, $description] = $name;

        // Create context
        $context = new CreateSimpleChannelContext(
            (int)$message->channel->parentID,
            $channelName,
            $description,
            (int)$this->everyoneRole,
            $message->guild,
            $message->client,
            $message->channel
        );
        // Create channel from context
        $this->channelCreator->create($context);
        $io->success(sprintf('Simple joinable channel %s created for %s', $channelName, $description));
        $message->delete();
    }
}
