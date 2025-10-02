<?php

declare(strict_types=1);

namespace Plushki\TgBot\Tests\Adapters\Telegram;

use Plushki\TgBot\Adapters\Telegram\CallbackQuery;
use Plushki\TgBot\Adapters\Telegram\Chat;
use Plushki\TgBot\Adapters\Telegram\Message;
use Plushki\TgBot\Adapters\Telegram\Update;
use PHPUnit\Framework\TestCase;

/**
 * Pure parsing of inbound Telegram update payloads (array -> DTO). No network.
 */
final class UpdateParsingTest extends TestCase
{
    public function testFromArrayParsesAMessageUpdate(): void
    {
        $u = Update::fromArray([
            'update_id' => 42,
            'message' => [
                'message_id' => 7,
                'text' => '/menu',
                'chat' => ['id' => 555, 'type' => 'private', 'username' => 'bob'],
            ],
        ]);

        self::assertSame(42, $u->updateId);
        self::assertNull($u->callbackQuery);
        self::assertInstanceOf(Message::class, $u->message);
        self::assertSame(7, $u->message->messageId);
        self::assertSame('/menu', $u->message->text);
        self::assertInstanceOf(Chat::class, $u->message->chat);
        self::assertSame(555, $u->message->chat->id);
        self::assertSame('private', $u->message->chat->type);
        self::assertSame('bob', $u->message->chat->username);
    }

    public function testFromArrayParsesACallbackQueryUpdate(): void
    {
        $u = Update::fromArray([
            'update_id' => 9,
            'callback_query' => [
                'id' => 'cb-1',
                'data' => 'place:abc',
                'message' => [
                    'message_id' => 3,
                    'chat' => ['id' => -100],
                ],
            ],
        ]);

        self::assertSame(9, $u->updateId);
        self::assertNull($u->message);
        self::assertInstanceOf(CallbackQuery::class, $u->callbackQuery);
        self::assertSame('cb-1', $u->callbackQuery->id);
        self::assertSame('place:abc', $u->callbackQuery->data);
        self::assertInstanceOf(Message::class, $u->callbackQuery->message);
        self::assertSame(-100, $u->callbackQuery->message->chat->id);
    }

    public function testFromArrayDefaultsForEmptyPayload(): void
    {
        $u = Update::fromArray([]);

        self::assertSame(0, $u->updateId);
        self::assertNull($u->message);
        self::assertNull($u->callbackQuery);
    }

    public function testCallbackQueryWithoutMessageIsNull(): void
    {
        $q = CallbackQuery::fromArray(['id' => 'x', 'data' => 'cancel:1']);

        self::assertSame('x', $q->id);
        self::assertSame('cancel:1', $q->data);
        self::assertNull($q->message);
    }

    public function testMessageCoercesNonStringTextAndMissingChat(): void
    {
        $m = Message::fromArray(['message_id' => '12', 'text' => 99]);

        self::assertSame(12, $m->messageId);
        self::assertSame('99', $m->text);
        // Missing chat -> defaulted Chat with id 0.
        self::assertInstanceOf(Chat::class, $m->chat);
        self::assertSame(0, $m->chat->id);
    }

    public function testChatDefaults(): void
    {
        $c = Chat::fromArray([]);

        self::assertSame(0, $c->id);
        self::assertSame('', $c->type);
        self::assertSame('', $c->username);
    }

    public function testMessageWithNonArrayChatFallsBackToDefaults(): void
    {
        // Update::fromArray guards the chat field; Message guards it too.
        $m = Message::fromArray(['message_id' => 1, 'chat' => 'not-an-array', 'text' => 'hi']);

        self::assertSame(0, $m->chat->id);
        self::assertSame('hi', $m->text);
    }
}
