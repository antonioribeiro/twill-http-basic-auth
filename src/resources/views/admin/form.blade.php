@extends('twill::layouts.form')

@php
    use A17\TwillHttpBasicAuth\Support\Facades\TwillHttpBasicAuth;
@endphp

@inject('twillHttpBasicAuth', TwillHttpBasicAuth::class)

@section('contentFields')

    @formField('input', [
        'type' => $twillHttpBasicAuth::config('inputs.username.type'),
        'name' => 'username',
        'label' => 'Username',
        'required' => true,
        'disabled' => $twillHttpBasicAuth::hasDotEnv(),
        'note' => 'Mandatory to publish the domain',
    ])

    @formField('input', [
        'type' => $twillHttpBasicAuth::config('inputs.password.type'),
        'name' => 'password',
        'label' => 'Password',
        'required' => true,
        'disabled' => $twillHttpBasicAuth::hasDotEnv(),
        'note' => 'Mandatory to publish the domain',
        'password' => true,
    ])

    @formField('checkbox', [
        'name' => 'allow_laravel_login',
        'label' => 'Allow Laravel users to login',
        'note' => 'People with an account on the website can pass the password prompt with their own e-mail and password, the same ones they use to log in to Laravel. A visitor who is only logged in (a session) does not pass: the prompt comes first.',
        'disabled' => $twillHttpBasicAuth::hasDotEnv(),
    ])

    @formField('checkbox', [
        'name' => 'allow_twill_login',
        'label' => 'Allow Twill users to login',
        'note' => 'People with an account on this admin can pass the password prompt with their own e-mail and password, the same ones they use to log in to Twill (published accounts only). A visitor who is only logged in (a session) does not pass: the prompt comes first.',
        'disabled' => $twillHttpBasicAuth::hasDotEnv(),
    ])
@stop
