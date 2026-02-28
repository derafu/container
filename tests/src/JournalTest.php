<?php

declare(strict_types=1);

/**
 * Derafu: Container - Flexible Data Containers for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsContainer;

use Derafu\Container\Journal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(Journal::class)]
class JournalTest extends TestCase
{
    private Journal $journal;

    /**
     * Sets up the test fixture.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->journal = new Journal();
    }

    /**
     * Tests adding and retrieving elements in normal and reverse order.
     *
     * @return void
     */
    public function testJournalAddAndRetrieveElements(): void
    {
        $this->journal->add('first');
        $this->journal->add('second');
        $this->journal->add('third');

        $this->assertSame(
            ['first', 'second', 'third'],
            $this->journal->all()
        );

        $this->assertSame(
            ['third', 'second', 'first'],
            $this->journal->reverse()
        );
    }

    /**
     * Tests clearing the journal.
     *
     * @return void
     */
    public function testJournalClearJournal(): void
    {
        $this->journal->add('item');
        $this->journal->clear();

        $this->assertSame([], $this->journal->all());
    }

    /**
     * Tests journal with different value types.
     *
     * @return void
     */
    public function testJournalJournalWithDifferentTypes(): void
    {
        $object = new stdClass();
        $array = ['key' => 'value'];
        $number = 42;

        $this->journal->add($object);
        $this->journal->add($array);
        $this->journal->add($number);

        $allItems = $this->journal->all();

        $this->assertCount(3, $allItems);
        $this->assertSame($object, $allItems[0]);
        $this->assertSame($array, $allItems[1]);
        $this->assertSame($number, $allItems[2]);
    }
}
