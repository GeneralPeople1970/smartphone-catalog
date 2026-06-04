<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizedValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_chinese_validation_messages_remain_available(): void
    {
        app()->setLocale('zh_CN');

        $response = $this->from('/register')->post('/register', []);

        $response
            ->assertRedirect('/register')
            ->assertSessionHasErrors([
                'name' => '名称 不能为空。',
                'email' => '邮箱 不能为空。',
                'password' => '密码 不能为空。',
            ]);
    }
}
