<?php

namespace A17\TwillHttpBasicAuth\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use A17\TwillHttpBasicAuth\Tests\TestCase;

/**
 * An encrypted value is longer than the text it hides (32 characters become 256): the columns that hold it are not `varchar(255)`.
 */
class ColumnsTest extends TestCase
{
    public function test_the_encrypted_credentials_fit_in_their_columns(): void
    {
        $types = [];

        foreach (Schema::getColumns('twill_basic_auth') as $column) {
            $types[$column['name']] = $column['type_name'];
        }

        $this->assertSame('text', $types['username'] ?? null);
        $this->assertSame('text', $types['password'] ?? null);
    }
}
