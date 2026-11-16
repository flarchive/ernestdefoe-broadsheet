<?php

/*
 * This file is part of ernestdefoe/broadsheet.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ErnestDefoe\Broadsheet\Tests\unit;

use ErnestDefoe\Broadsheet\Front\Story;
use ErnestDefoe\Broadsheet\Settings;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SettingsTest extends TestCase
{
    #[Test]
    public function sections_parse_to_unique_positive_ids_capped_at_twelve(): void
    {
        $this->assertSame([3, 1], Settings::parseSections('["3","1","3","0","-2","x"]'));
        $this->assertSame([], Settings::parseSections('not json'));
        $this->assertSame([], Settings::parseSections(null));
        $this->assertCount(12, Settings::parseSections(json_encode(range(1, 20))));
    }

    #[Test]
    public function only_a_hex_colour_reaches_the_stylesheet(): void
    {
        $this->assertSame('#A4231B', Settings::hex('#a4231b'));
        $this->assertSame('#FFF', Settings::hex(' #fff '));
        $this->assertNull(Settings::hex('red; } body { display:none'));
        $this->assertNull(Settings::hex(''));
    }

    #[Test]
    public function only_safe_picture_addresses_get_through(): void
    {
        $this->assertSame('https://a.test/b.png', Story::safe('https://a.test/b.png'));
        $this->assertSame('/assets/files/b.png', Story::safe('/assets/files/b.png'));
        $this->assertNull(Story::safe('javascript:alert(1)'));
        $this->assertNull(Story::safe('//evil.test/x.png'));
        $this->assertNull(Story::safe('https://a.test/b.png" onerror="x'));
    }
}
