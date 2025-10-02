<?php

declare(strict_types=1);

namespace Plushki\Identity\Tests\App;

use PHPUnit\Framework\TestCase;
use Plushki\Identity\App\MeService;
use Plushki\Identity\Domain\DomainException;
use Plushki\Identity\Domain\ErrorCode;
use Plushki\Identity\Domain\User;
use Plushki\Identity\Tests\Fake\FakeUserRepo;

final class MeServiceTest extends TestCase
{
    public function testGetReturnsUser(): void
    {
        $users = new FakeUserRepo();
        $u = new User('uid', 'default', 'a@b.com', 'h', 'Alice', ['user'], new \DateTimeImmutable('now'));
        $users->add($u);

        $svc = new MeService($users);
        self::assertSame('a@b.com', $svc->get('uid')->email);
    }

    public function testGetMissingThrowsUserNotFound(): void
    {
        $svc = new MeService(new FakeUserRepo());
        try {
            $svc->get('nope');
            self::fail('expected DomainException');
        } catch (DomainException $e) {
            self::assertSame(ErrorCode::UserNotFound, $e->errorCode);
        }
    }
}
