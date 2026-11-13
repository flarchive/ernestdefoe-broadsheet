<?php

/*
 * This file is part of ernestdefoe/broadsheet.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ErnestDefoe\Broadsheet\Tests\integration\api;

use ErnestDefoe\Broadsheet\Tests\integration\BroadsheetTestCase;
use PHPUnit\Framework\Attributes\Test;

class PreloadTest extends BroadsheetTestCase
{
    private function page(string $path, array $query = []): string
    {
        return (string) $this->send($this->request('GET', $path)->withQueryParams($query))->getBody();
    }

    #[Test]
    public function the_front_page_ships_its_stories_in_the_page(): void
    {
        $this->assertStringContainsString('"broadsheetFront"', $this->page('/'));
        $this->assertStringContainsString('"broadsheetFront"', $this->page('/all'));
    }

    #[Test]
    public function other_pages_ship_nothing(): void
    {
        $this->assertStringNotContainsString('"broadsheetFront"', $this->page('/t/news'));
        $this->assertStringNotContainsString('"broadsheetFront"', $this->page('/all', ['q' => 'ferry']));
        $this->assertStringNotContainsString('"broadsheetFront"', $this->page('/all', ['page' => '2']));
        $this->assertStringNotContainsString('"broadsheetFront"', $this->page('/d/1'));
    }
}
