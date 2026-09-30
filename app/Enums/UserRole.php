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
            self::Admin => 'Xem và sửa mọi phiếu, phân công lại phiếu, xoá phiếu lập nhầm, xem báo cáo, quản lý tài khoản, ẩn / hiện danh mục và TTBH',
            self::User => 'Lập và xử lý phiếu của mình (tự là người phụ trách), chỉ thấy phiếu mình lập hoặc được Admin phân công',
        };
    }
}
