<?php

use PHPUnit\Framework\TestCase;

final class MenuLogicTest extends TestCase {

    public function test_visibility_never_is_always_hidden() {
        $this->assertFalse( wpadmin_button_resolve_visibility( 'never', true ) );
        $this->assertFalse( wpadmin_button_resolve_visibility( 'never', false ) );
    }

    public function test_visibility_always_is_always_shown() {
        $this->assertTrue( wpadmin_button_resolve_visibility( 'always', true ) );
        $this->assertTrue( wpadmin_button_resolve_visibility( 'always', false ) );
    }

    public function test_visibility_auto_follows_toolbar_hidden_state() {
        $this->assertTrue( wpadmin_button_resolve_visibility( 'auto', true ) );
        $this->assertFalse( wpadmin_button_resolve_visibility( 'auto', false ) );
    }

    public function test_visibility_unknown_mode_defaults_to_auto() {
        $this->assertTrue( wpadmin_button_resolve_visibility( 'bogus', true ) );
        $this->assertFalse( wpadmin_button_resolve_visibility( '', false ) );
    }
}
