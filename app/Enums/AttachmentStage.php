<?php

namespace App\Enums;

/**
 * When a photo or document was taken during the ticket's life.
 */
enum AttachmentStage: string
{
    case Intake = 'intake';
    case Inspection = 'inspection';
    case SendToCenter = 'send_to_center';
    case BackFromCenter = 'back_from_center';
    case VendorVisit = 'vendor_visit';
    case Return = 'return';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Intake => 'Lúc nhận máy',
            self::Inspection => 'Kiểm tra / sửa chữa',
            self::SendToCenter => 'Lúc gửi TTBH',
            self::BackFromCenter => 'Lúc nhận về từ TTBH',
            self::VendorVisit => 'Hãng xử lý',
            self::Return => 'Lúc trả máy',
            self::Other => 'Bổ sung',
        };
    }

    /**
     * What to photograph, shown under the upload input.
     */
    public function hint(): string
    {
        return match ($this) {
            self::Intake => 'Chụp tình trạng máy khi khách giao: các mặt máy, vết trầy / nứt, tem bảo hành, phụ kiện kèm theo.',
            self::Inspection => 'Lỗi phát hiện khi kiểm tra, linh kiện hỏng, máy sau khi sửa…',
            self::SendToCenter => 'Tình trạng máy trước khi giao TTBH, phiếu gửi / biên nhận của TTBH…',
            self::BackFromCenter => 'Tình trạng máy khi nhận về, phiếu trả của TTBH…',
            self::VendorVisit => 'Máy sau khi hãng xử lý, phiếu / biên bản của hãng…',
            self::Return => 'Chụp tình trạng máy khi giao lại cho khách, phiếu trả có chữ ký khách…',
            self::Other => 'Ảnh hoặc chứng từ bổ sung.',
        };
    }

    /**
     * Stages that must always keep at least one photo once they have happened.
     */
    public function requiresPhoto(): bool
    {
        return $this === self::Intake || $this === self::Return;
    }

    /**
     * Document type given to a non-image file (PDF) uploaded at this stage.
     */
    public function documentType(): AttachmentType
    {
        return match ($this) {
            self::BackFromCenter => AttachmentType::CenterReturn,
            self::VendorVisit => AttachmentType::VendorNote,
            default => AttachmentType::Other,
        };
    }
}
