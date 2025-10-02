<?php

declare(strict_types=1);

namespace Plushki\Identity\Tests\App;

use PHPUnit\Framework\TestCase;
use Plushki\Identity\App\UserAdminService;
use Plushki\Identity\Domain\DomainException;
use Plushki\Identity\Domain\ErrorCode;
use Plushki\Identity\Domain\User;
use Plushki\Identity\Ports\UserListParams;
use Plushki\Identity\Tests\Fake\FakeUserRepo;

final class UserAdminServiceTest extends TestCase
{
    private FakeUserRepo $users;
    private UserAdminService $svc;

    protected function setUp(): void
    {
        $this->users = new FakeUserRepo();
        $this->svc = new UserAdminService($this->users);
    }

    private function seed(string $email = 'a@b.com', string $display = 'Alice', array $roles = ['user'], ?\DateTimeImmutable $archived = null): User
    {
        $u = new User(
            'id-' . $email, 'default', $email, User::hashPassword('password123'),
            $display, $roles, new \DateTimeImmutable('now'), $archived,
        );
        $this->users->add($u);

        return $u;
    }

    public function testGetReturnsUser(): void
    {
        $u = $this->seed();
        self::assertSame($u->id, $this->svc->get($u->id)->id);
    }

    public function testGetMissingThrowsUserNotFound(): void
    {
        $this->assertCode(ErrorCode::UserNotFound, fn () => $this->svc->get('nope'));
    }

    public function testUpdateProfileChangesDisplayName(): void
    {
        $u = $this->seed();
        $updated = $this->svc->updateProfile($u->id, 'New Name');
        self::assertSame('New Name', $updated->displayName);
        self::assertSame($u->email, $updated->email, 'email immutable');
    }

    public function testUpdateRolesHappyPath(): void
    {
        $u = $this->seed();
        $updated = $this->svc->updateRoles($u->id, ['admin', 'baker']);
        self::assertSame(['admin', 'baker'], $updated->roles);
    }

    public function testUpdateRolesDeduplicatesAndDropsEmpty(): void
    {
        $u = $this->seed();
        $updated = $this->svc->updateRoles($u->id, ['admin', '', 'admin', 'baker']);
        self::assertSame(['admin', 'baker'], $updated->roles);
    }

    public function testUpdateRolesRejectsUnknownRole(): void
    {
        $u = $this->seed();
        $this->assertCode(ErrorCode::InvalidRole, fn () => $this->svc->updateRoles($u->id, ['admin', 'superuser']));
    }

    public function testUpdateRolesRejectsEmptyResult(): void
    {
        $u = $this->seed();
        $this->assertCode(ErrorCode::InvalidRole, fn () => $this->svc->updateRoles($u->id, ['', '']));
    }

    public function testUpdateRolesRejectsEmptyList(): void
    {
        $u = $this->seed();
        $this->assertCode(ErrorCode::InvalidRole, fn () => $this->svc->updateRoles($u->id, []));
    }

    public function testResetPasswordOverwritesHash(): void
    {
        $u = $this->seed();
        $oldHash = $this->users->byId[$u->id]->passwordHash;
        $this->svc->resetPassword($u->id, 'newpassword1');
        $newHash = $this->users->byId[$u->id]->passwordHash;

        self::assertNotSame($oldHash, $newHash);
        self::assertTrue(password_verify('newpassword1', $newHash));
    }

    public function testResetPasswordRejectsShort(): void
    {
        $u = $this->seed();
        $this->assertCode(ErrorCode::PasswordTooShort, fn () => $this->svc->resetPassword($u->id, 'short'));
    }

    public function testSetArchivedTrueThenFalse(): void
    {
        $u = $this->seed();
        $archived = $this->svc->setArchived($u->id, true);
        self::assertTrue($archived->isArchived());

        $restored = $this->svc->setArchived($u->id, false);
        self::assertFalse($restored->isArchived());
    }

    public function testListFiltersArchivedByDefault(): void
    {
        $this->seed('a@b.com', 'Alice');
        $this->seed('c@d.com', 'Carol', ['user'], new \DateTimeImmutable('now'));

        $active = $this->svc->list(new UserListParams());
        self::assertCount(1, $active);
        self::assertSame('a@b.com', $active[0]->email);

        $all = $this->svc->list(new UserListParams(includeArchived: true));
        self::assertCount(2, $all);
    }

    public function testListSearchByQuery(): void
    {
        $this->seed('alice@b.com', 'Alice');
        $this->seed('bob@b.com', 'Bob');

        $hits = $this->svc->list(new UserListParams(q: 'bob'));
        self::assertCount(1, $hits);
        self::assertSame('bob@b.com', $hits[0]->email);
    }

    private function assertCode(ErrorCode $expected, callable $fn): void
    {
        try {
            $fn();
            self::fail('expected DomainException ' . $expected->value);
        } catch (DomainException $e) {
            self::assertSame($expected, $e->errorCode);
        }
    }
}
