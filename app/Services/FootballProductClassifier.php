<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Lọc và phân loại sản phẩm bóng đá từ dữ liệu thô của sàn thương mại điện tử.
 * Chỉ giữ sản phẩm có dấu hiệu bóng đá rõ ràng; loại bóng rổ, bóng chuyền, tennis...
 */
final class FootballProductClassifier
{
    public const CATEGORY_NAMES = [
        'giay-bong-da' => 'Giày bóng đá',
        'ao-dau' => 'Áo đấu',
        'bong' => 'Bóng',
        'phu-kien' => 'Phụ kiện',
        'khac' => 'Khác',
    ];

    private const FOOTBALL_TERMS = [
        'bóng đá', 'bong da', 'football', 'soccer', 'futsal',
        'đá bóng', 'da bong', 'đá banh', 'da banh',
        'giày đá bóng', 'giay da bong', 'giày đá banh', 'giay da banh',
        'giầy đá bóng', 'giầy đá banh', 'giày bóng đá', 'giay bong da', 'giầy bóng đá',
        'áo đấu', 'ao dau', 'áo bóng đá', 'ao bong da',
        'đồng phục thi đấu', 'dong phuc thi dau', 'đồng phục bóng đá', 'dong phuc bong da',
        'quả bóng đá', 'qua bong da', 'bóng thi đấu', 'bong thi dau',
        'thủ môn', 'thu mon', 'găng tay thủ môn', 'gang tay thu mon',
        'sân cỏ', 'san co', 'sân cỏ nhân tạo', 'san co nhan tao', 'cleats', 'jersey',
        'boot đá bóng', 'boot da bong', 'đinh đá bóng', 'dinh da bong',
        'băng đội trưởng', 'bang doi truong', 'ống đồng', 'ong dong',
        'phụ kiện bóng đá', 'phu kien bong da',
        'quần áo bóng đá', 'quan ao bong da', 'áo tập bóng đá', 'ao tap bong da',
        'quần short bóng đá', 'quan short bong da',
        'vớ bóng đá', 'vo bong da', 'tất bóng đá', 'tat bong da',
        'balo bóng đá', 'balo bong da', 'túi bóng đá', 'tui bong da',
        'lưới bóng đá', 'luoi bong da', 'bơm bóng', 'bom bong', 'kim bơm', 'kim bom',
        'còi bóng đá', 'coi bong da',
    ];

    private const STRICT_FOOTBALL_TERMS = [
        'bóng đá', 'bong da', 'football', 'soccer', 'futsal',
        'đá bóng', 'da bong', 'đá banh', 'da banh',
        'giày đá bóng', 'giay da bong', 'giày đá banh', 'giay da banh',
        'giầy đá bóng', 'giầy đá banh', 'giày bóng đá', 'giay bong da', 'giầy bóng đá',
        'quả bóng đá', 'qua bong da', 'bóng thi đấu', 'bong thi dau',
        'thủ môn', 'thu mon', 'găng tay thủ môn', 'gang tay thu mon',
        'cleats', 'boot đá bóng', 'boot da bong', 'đinh đá bóng', 'dinh da bong',
        'băng đội trưởng', 'bang doi truong', 'ống đồng', 'ong dong',
        'phụ kiện bóng đá', 'phu kien bong da',
        'quần áo bóng đá', 'quan ao bong da', 'áo bóng đá', 'ao bong da',
        'áo đấu', 'ao dau', 'đồng phục thi đấu', 'dong phuc thi dau',
        'đồng phục bóng đá', 'dong phuc bong da',
    ];

    private const OTHER_SPORTS = [
        'bóng rổ', 'bong ro', 'basketball',
        'bóng chuyền', 'bong chuyen', 'volleyball',
        'cầu lông', 'cau long', 'badminton',
        'tennis', 'quần vợt', 'quan vot',
        'golf', 'pickleball', 'bóng bàn', 'bong ban', 'table tennis',
        'bóng ném', 'bong nem', 'handball', 'bóng chày', 'bong chay', 'baseball',
        'bơi lội', 'boi loi', 'swimming', 'chạy bộ', 'chay bo', 'running',
        'xe đạp', 'xe dap', 'cycling', 'thể hình', 'the hinh', 'gym',
    ];

    private const CATEGORY_KEYWORDS = [
        'giay-bong-da' => [
            'giày bóng đá', 'giay bong da', 'giày đá bóng', 'giay da bong',
            'giày đá banh', 'giay da banh', 'giầy đá bóng', 'giầy đá banh',
            'giầy bóng đá', 'đá bóng', 'da bong', 'đá banh', 'da banh',
            'boot bóng đá', 'boot bong da', 'giày futsal', 'giay futsal',
            'giày', 'giay', 'boot', 'cleats', 'đinh', 'dinh', 'sân cỏ', 'san co',
            'sân cỏ nhân tạo', 'san co nhan tao', 'sân 5', 'sân 7', 'sân 11',
            'turf', 'fg', 'ag', 'sg', 'tf', 'predator', 'mercurial',
            'phantom', 'tiempo', 'copa', 'speedportal', 'future', 'ultra',
            'morelia', 'monarcida', 'rebula', 'tekela', 'kaizer', 'puma future',
        ],
        'ao-dau' => [
            'áo đấu', 'ao dau', 'áo bóng đá', 'ao bong da', 'jersey', 'kit',
            'áo tập', 'ao tap', 'quần áo', 'quan ao', 'training', 'polo',
            'đồng phục thi đấu', 'dong phuc thi dau', 'đồng phục bóng đá', 'dong phuc bong da',
            'bộ quần áo bóng đá', 'bo quan ao bong da',
            'retro', 'sân nhà', 'san nha', 'sân khách', 'san khach',
            'third kit', 'home kit', 'away kit',
        ],
        'bong' => [
            'quả bóng', 'qua bong', 'banh bóng đá', 'banh bong da',
            'bóng thi đấu', 'bong thi dau',
            'bóng đá thi đấu', 'bong da thi dau',
            'bóng tập', 'bong tap', 'bóng futsal', 'bong futsal',
            'bóng size', 'bong size', 'bóng số', 'bong so',
            'size 5', 'size 4', 'size 3',
            'world cup', 'champions league', 'futsal ball',
        ],
        'phu-kien' => [
            'tất', 'tat', 'vớ', 'vo', 'găng tay', 'gang tay', 'gloves',
            'găng tay thủ môn', 'gang tay thu mon',
            'băng đội trưởng', 'bang doi truong', 'ống đồng', 'ong dong',
            'shin', 'túi', 'tui', 'balo', 'bình nước', 'binh nuoc',
            'vớ bóng đá', 'vo bong da', 'tất bóng đá', 'tat bong da',
            'balo bóng đá', 'balo bong da', 'túi bóng đá', 'tui bong da',
            'khăn', 'khan', 'mũ', 'mu', 'nón', 'non', 'cờ', 'co',
            'móc khóa', 'moc khoa', 'lưới', 'luoi', 'còi', 'coi',
            'bơm', 'bom', 'kim bơm', 'kim bom', 'giá đỡ', 'gia do',
            'đinh giày', 'dinh giay', 'đế giày', 'de giay',
            'thẻ', 'the', 'thẻ trọng tài', 'the trong tai',
            'bảng thay người', 'bang thay nguoi', 'bảng chiến thuật', 'bang chien thuat',
            'bảng tính điểm', 'bang tinh diem', 'khung thành', 'khung thanh',
            'cầu môn', 'cau mon', 'nấm tập', 'nam tap', 'thang dây', 'thang day',
            'bó gối', 'bo goi', 'băng khuỷu', 'bang khuyu', 'băng khủy', 'bang khuy',
            'bảo vệ đùi', 'bao ve dui', 'bó gót', 'bo got', 'băng', 'bang',
            'băng tay', 'bang tay', 'băng cổ chân', 'bang co chan',
            'xịt', 'xit', 'bình xịt', 'binh xit', 'dầu xoa', 'dau xoa', 'cao xoa',
        ],
    ];

    public function isFootball(string $text): bool
    {
        $text = $this->normalize($text);
        if ($text === '' || !$this->hasAny($text, self::FOOTBALL_TERMS)) {
            return false;
        }
        if ($this->hasAny($text, self::OTHER_SPORTS) && !$this->hasAny($text, self::STRICT_FOOTBALL_TERMS)) {
            return false;
        }
        return true;
    }

    /**
     * Kiểm tra theo tiêu đề + ngữ cảnh danh mục. Nếu tiêu đề nói rõ môn khác
     * (pickleball, bóng chuyền...) thì loại, kể cả khi Tiki xếp nhầm vào mục Bóng Đá.
     */
    public function isFootballProduct(string $title, string $context = ''): bool
    {
        $titleText = $this->normalize($title);
        if ($titleText !== ''
            && $this->hasAny($titleText, self::OTHER_SPORTS)
            && !$this->hasAny($titleText, self::STRICT_FOOTBALL_TERMS)) {
            return false;
        }
        return $this->isFootball($title . ' ' . $context);
    }

    /**
     * Từ khóa ở tên sản phẩm có trọng số cao hơn mô tả để tránh việc mô tả
     * nhắc đến "giày" làm sai loại của tất, balo, bóng...
     */
    public function classifyProduct(string $title, string $description = '', string $category = ''): string
    {
        $categoryText = $this->normalize($category);
        if ($categoryText !== '' && $this->hasAny($categoryText, [
            'sách', 'truyện', 'đồ chơi', 'đồng hồ', 'thời trang',
        ])) {
            return 'khac';
        }
        if ($this->looksLikeBall($title, $category)) {
            return 'bong';
        }
        $titleText = $this->normalize($title . ' ' . $category);
        $descriptionText = $this->normalize($description);
        $scores = array_fill_keys(array_keys(self::CATEGORY_NAMES), 0);
        foreach (self::CATEGORY_KEYWORDS as $slug => $keywords) {
            foreach ($keywords as $keyword) {
                if ($keyword === '') {
                    continue;
                }
                $weight = strpos($keyword, ' ') !== false ? 3 : 1;
                if (mb_strpos($titleText, $keyword, 0, 'UTF-8') !== false) {
                    $scores[$slug] += $weight * 4;
                } elseif (mb_strpos($descriptionText, $keyword, 0, 'UTF-8') !== false) {
                    $scores[$slug] += $weight;
                }
            }
        }

        $best = 'khac';
        $bestScore = 0;
        foreach (array_keys(self::CATEGORY_NAMES) as $slug) {
            if ($slug === 'khac') {
                continue;
            }
            if ($scores[$slug] > $bestScore) {
                $best = $slug;
                $bestScore = $scores[$slug];
            }
        }
        return $best;
    }

    /** Nhận diện tiêu đề là bóng (bắt đầu bằng "quả bóng", "banh", "bóng đá"...). */
    private function looksLikeBall(string $title, string $category = ''): bool
    {
        $text = $this->normalize($title);
        if ($text === '') {
            return false;
        }
        $categoryText = $this->normalize($category);
        if ($categoryText !== '' && $this->hasAny($categoryText, [
            'sách', 'truyện', 'đồ chơi', 'đồng hồ', 'thời trang', 'quần áo', 'giày', 'áo',
            'băng', 'bó gối', 'nẹp', 'balo', 'túi', 'mũ', 'nón', 'găng',
        ])) {
            return false;
        }
        $accessoryWords = [
            'giày', 'giay', 'giầy', 'áo', 'găng', 'balo', 'túi',
            'bảng', 'thẻ', 'khung', 'nẹp', 'quần', 'đồng phục',
            'mũ', 'nón', 'bó gối', 'băng', 'đai', 'sách', 'truyện',
            'đồ chơi', 'cầu thủ', 'đồng hồ', 'xịt', 'dầu', 'bình', 'ống', 'nấm',
            'thang dây', 'quần thun',
        ];
        foreach ([
            'quả bóng', 'qua bong', 'banh',
            'bóng số', 'bong so', 'bóng size', 'bong size',
            'bóng futsal', 'bong futsal', 'bóng thi đấu', 'bong thi dau',
            'bóng tập', 'bong tap', 'bóng da', 'bong da', 'bóng mini', 'bong mini',
        ] as $prefix) {
            if (str_starts_with($text, $prefix)) {
                return true;
            }
        }
        if (str_starts_with($text, 'bóng đá') || str_starts_with($text, 'bong da')) {
            foreach ($accessoryWords as $word) {
                if (mb_strpos($text, $word, 0, 'UTF-8') !== false) {
                    return false;
                }
            }
            return true;
        }
        // Tiêu đề có số/model phía trước, ví dụ "131 Bóng Đá ĐL UM135 Số 4".
        if (mb_strpos($text, 'bóng đá', 0, 'UTF-8') !== false
            || mb_strpos($text, 'bong da', 0, 'UTF-8') !== false) {
            foreach ($accessoryWords as $word) {
                if (mb_strpos($text, $word, 0, 'UTF-8') !== false) {
                    return false;
                }
            }
            return true;
        }
        return false;
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = str_replace(["\xC2\xA0", "\xE2\x80\x8B"], ' ', $text);
        $text = (string) preg_replace('/\s+/u', ' ', $text);
        return $text;
    }

    /** @param array<int, string> $needles */
    private function hasAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && mb_strpos($text, $needle, 0, 'UTF-8') !== false) {
                return true;
            }
        }
        return false;
    }
}
