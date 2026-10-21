<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class UiHelperTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('ui');
    }

    public function testInitials(): void
    {
        $this->assertSame('AP', initials('Andi Pratama'));
        $this->assertSame('NA', initials('Nadia Putri Anggraini'));
        $this->assertSame('HE', initials('Hendra'));
        $this->assertSame('?', initials('  '));
    }

    public function testMaskId(): void
    {
        $this->assertSame('3174•••••••••012', mask_id('3174012345678012'));
        $this->assertSame('—', mask_id(null));
        $this->assertSame('•••', mask_id('123'));
    }

    public function testAvatarColorIsDeterministic(): void
    {
        $this->assertSame(avatar_color('Andi Pratama'), avatar_color('Andi Pratama'));
        $this->assertStringStartsWith('from-', avatar_color('Siti Nurhaliza'));
    }

    public function testStatusBadgeEscapesLabel(): void
    {
        $html = status_badge('paid');
        $this->assertStringContainsString('Dibayar', $html);
        $this->assertStringContainsString('bg-emerald-50', $html);

        $this->assertStringNotContainsString('<script>', status_badge('draft', '<script>x</script>'));
    }
}
