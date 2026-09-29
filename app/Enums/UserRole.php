<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case User = 'user';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::User => 'User',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Admin => 'Toàn quyền, quản lý tài khoản, ẩn / hiện danh mục và TTBH',
            self::User => 'Lập và xử lý phiếu, thêm khách hàng, danh mục, chứng từ',
        };
    }
}
