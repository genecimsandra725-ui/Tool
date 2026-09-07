const path = require('path');

const ROOT = path.resolve(__dirname, '..');

module.exports = {
  root: ROOT,
  publicDir: path.join(ROOT, 'public'),
  dataFile: path.join(ROOT, 'data', 'sites.json'),
  port: Number(process.env.PORT || 80),
  secret: process.env.SECRET || 'amin-toolbox-change-me',
  cookieName: 'toolbox_token',
  tokenTtlDays: 7,
  db: {
    host: process.env.DB_HOST || '127.0.0.1',
    port: Number(process.env.DB_PORT || 3306),
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_NAME || 'toolbox_nav',
    charset: 'utf8mb4',
    connectionLimit: 10,
  },
  site: {
    title: '阿旻同学 | 工具箱',
    name: '阿旻同学工具箱',
    description:
      '阿旻同学整理的实用网站导航，覆盖 AI、设计、开发、学习、工具、游戏、影音与生活分类。',
    keywords: '网站导航,工具箱,AI工具,设计素材,开发开源,学习资源,效率工具',
    icp: '京ICP备00000000号-1',
    updatedAt: '2026-09-07',
    sourceUrl:
      'https://ncnjicnef38d.feishu.cn/wiki/PHAtw00YAiKcYLkGYyHc6yVmn6d',
  },
  friendLinks: [
    { name: '飞书', url: 'https://www.feishu.cn/' },
    { name: 'GitHub', url: 'https://github.com/' },
    { name: 'MDN Web Docs', url: 'https://developer.mozilla.org/zh-CN/' },
    { name: '少数派', url: 'https://sspai.com/' },
    { name: '菜鸟教程', url: 'https://www.runoob.com/' },
    { name: 'Dribbble', url: 'https://dribbble.com/' },
  ],
  categories: [
    { slug: 'ai', name: 'AI 工具', icon: '🤖', desc: '提示词、生图、模型与 AI 助手' },
    { slug: 'tool', name: '效率工具', icon: '🛠️', desc: '文件、PDF、图片与日常在线工具' },
    { slug: 'learning', name: '学习成长', icon: '📚', desc: '课程、外语、阅读与自我提升' },
    { slug: 'dev', name: '开发开源', icon: '💻', desc: 'GitHub、代码、文档与开源项目' },
    { slug: 'design', name: '设计素材', icon: '🎨', desc: '配色、字体、图标与创意素材' },
    { slug: 'game', name: '游戏娱乐', icon: '🎮', desc: '小游戏、桌游、怀旧与在线娱乐' },
    { slug: 'media', name: '影音资源', icon: '🎬', desc: '视频、音乐、直播与媒体工具' },
    { slug: 'life', name: '生活实用', icon: '🏡', desc: '健康、美食、旅行与家庭生活' },
  ],
};
