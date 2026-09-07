<?php

return [
    'siteTitle' => '阿旻同学 | 工具箱',
    'siteName' => '阿旻同学工具箱',
    'siteDescription' => '阿旻同学整理的实用网站导航，覆盖 AI、设计、开发、学习、工具、游戏、影音与生活分类。',
    'siteKeywords' => '网站导航,工具箱,AI工具,设计素材,开发开源,学习资源,效率工具',
    'siteBaseUrl' => '', // 部署后填 https://your-domain.com，留空则自动生成
    'sourceUrl' => 'https://ncnjicnef38d.feishu.cn/wiki/PHAtw00YAiKcYLkGYyHc6yVmn6d',
    'updatedAt' => '2026-09-07',
    'icpNumber' => '京ICP备00000000号-1',
    'friendLinks' => [
        ['name' => '飞书', 'url' => 'https://www.feishu.cn/'],
        ['name' => 'GitHub', 'url' => 'https://github.com/'],
        ['name' => 'MDN Web Docs', 'url' => 'https://developer.mozilla.org/zh-CN/'],
        ['name' => '少数派', 'url' => 'https://sspai.com/'],
        ['name' => '菜鸟教程', 'url' => 'https://www.runoob.com/'],
        ['name' => 'Dribbble', 'url' => 'https://dribbble.com/'],
    ],
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'toolbox_nav',
        'user' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'categories' => [
        [
            'slug' => 'ai',
            'name' => 'AI 工具',
            'icon' => '🤖',
            'desc' => '提示词、生图、模型与 AI 助手',
        ],
        [
            'slug' => 'tool',
            'name' => '效率工具',
            'icon' => '🛠️',
            'desc' => '文件、PDF、图片与日常在线工具',
        ],
        [
            'slug' => 'learning',
            'name' => '学习成长',
            'icon' => '📚',
            'desc' => '课程、外语、阅读与自我提升',
        ],
        [
            'slug' => 'dev',
            'name' => '开发开源',
            'icon' => '💻',
            'desc' => 'GitHub、代码、文档与开源项目',
        ],
        [
            'slug' => 'design',
            'name' => '设计素材',
            'icon' => '🎨',
            'desc' => '配色、字体、图标与创意素材',
        ],
        [
            'slug' => 'game',
            'name' => '游戏娱乐',
            'icon' => '🎮',
            'desc' => '小游戏、桌游、怀旧与在线娱乐',
        ],
        [
            'slug' => 'media',
            'name' => '影音资源',
            'icon' => '🎬',
            'desc' => '视频、音乐、直播与媒体工具',
        ],
        [
            'slug' => 'life',
            'name' => '生活实用',
            'icon' => '🏡',
            'desc' => '健康、美食、旅行与家庭生活',
        ],
    ],
];
