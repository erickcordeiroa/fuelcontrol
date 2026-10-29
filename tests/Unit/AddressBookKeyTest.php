<?php

namespace Tests\Unit;

use App\Services\Geocoding\AddressBook;
use PHPUnit\Framework\TestCase;

class AddressBookKeyTest extends TestCase
{
    public function test_same_address_written_differently_has_the_same_key(): void
    {
        $this->assertSame(
            AddressBook::keyFor(['street' => 'Avenida Wild José de Souza', 'number' => '2162', 'city' => 'Registro', 'state' => 'SP']),
            AddressBook::keyFor(['street' => '  av.  wild jose de souza ', 'number' => 'nº 2162', 'city' => 'REGISTRO', 'state' => 'sp']),
        );
    }

    public function test_number_and_city_are_part_of_the_key(): void
    {
        $base = ['street' => 'Rua Shitiro Maeji', 'number' => '410', 'city' => 'Registro', 'state' => 'SP'];

        $this->assertNotSame(AddressBook::keyFor($base), AddressBook::keyFor([...$base, 'number' => '411']));
        $this->assertNotSame(AddressBook::keyFor($base), AddressBook::keyFor([...$base, 'city' => 'Iguape']));
        $this->assertNotSame(AddressBook::keyFor($base), AddressBook::keyFor([...$base, 'number' => null]));
    }
}
