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

    public function test_filter_keeps_admin_order_and_drops_hidden_and_uncapable() {
        $ordered = array( 'edit_current', 'dashboard', 'posts', 'plugins' );
        $hidden  = array( 'posts' );
        $can_use = function ( $key ) {
            $blocked = array( 'plugins', 'edit_current' );
            return ! in_array( $key, $blocked, true );
        };

        $result = wpadmin_button_filter_menu_items( $ordered, $hidden, $can_use );

        $this->assertSame( array( 'dashboard' ), $result );
    }

    public function test_filter_preserves_order_when_nothing_removed() {
        $ordered = array( 'dashboard', 'posts', 'media' );
        $can_use = function () {
            return true;
        };

        $result = wpadmin_button_filter_menu_items( $ordered, array(), $can_use );

        $this->assertSame( array( 'dashboard', 'posts', 'media' ), $result );
    }
}
