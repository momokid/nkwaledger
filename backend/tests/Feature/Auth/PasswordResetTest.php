<?php

test('reset password link screen can be rendered', function () {
    $response = $this->get('/forgot-password');

    $response->assertStatus(200);
})->skip('Password reset via email is being replaced with OTP-based reset via phone. Revisit in Phase 1 password reset feature.');

test('reset password link can be requested', function () {
    //
})->skip('Password reset via email is being replaced with OTP-based reset via phone. Revisit in Phase 1 password reset feature.');

test('reset password screen can be rendered', function () {
    //
})->skip('Password reset via email is being replaced with OTP-based reset via phone. Revisit in Phase 1 password reset feature.');

test('password can be reset with valid token', function () {
    //
})->skip('Password reset via email is being replaced with OTP-based reset via phone. Revisit in Phase 1 password reset feature.');
