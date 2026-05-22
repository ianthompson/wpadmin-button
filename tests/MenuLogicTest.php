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

    private function valid_keys() {
        return array( 'edit_current', 'dashboard', 'posts', 'media', 'pages', 'comments', 'appearance', 'plugins', 'users', 'profile', 'tools', 'settings' );
    }

    public function test_seed_includes_edit_current_and_dashboard_plus_previous() {
        $result = wpadmin_button_seed_menu_items( 'posts', $this->valid_keys() );
        $this->assertSame( array( 'edit_current', 'dashboard', 'posts' ), $result );
    }

    public function test_seed_dedupes_when_previous_already_seeded() {
        $result = wpadmin_button_seed_menu_items( 'dashboard', $this->valid_keys() );
        $this->assertSame( array( 'edit_current', 'dashboard' ), $result );
    }

    public function test_seed_ignores_unknown_previous_destination() {
        $result = wpadmin_button_seed_menu_items( 'nonsense', $this->valid_keys() );
        $this->assertSame( array( 'edit_current', 'dashboard' ), $result );
    }

    public function test_seed_handles_empty_previous() {
        $result = wpadmin_button_seed_menu_items( '', $this->valid_keys() );
        $this->assertSame( array( 'edit_current', 'dashboard' ), $result );
    }
}
