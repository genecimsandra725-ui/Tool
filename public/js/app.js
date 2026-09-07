import * as Vue from '/vendor/vue.esm-browser.prod.js';

const { createApp } = Vue;

  const STATUS_LABELS = {
    pending: '待处理',
    processing: '处理中',
    resolved: '已解决',
    closed: '已关闭',
  };
  const TYPE_LABELS = {
    broken: '链接失效',
    suggestion: '功能建议',
    other: '其他',
  };

  async function request(url, options = {}) {
    const response = await fetch(url, {
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      ...options,
    });
    let data = {};
    try {
      data = await response.json();
    } catch (error) {
      data = { ok: false, error: '服务响应异常' };
    }
    if (!response.ok || data.ok === false) {
      throw new Error(data.error || '请求失败');
    }
    return data;
  }

  const TEMPLATE = `
  <div class="app" :class="{ 'is-collapsed': sidebarCollapsed, 'is-mobile-open': mobileOpen }">
    <header class="topbar">
      <div class="topbar-inner">
        <button class="icon-btn" type="button" aria-label="展开分类" @click="toggleSidebar">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
        </button>
        <a class="brand" href="#" @click.prevent="goHome">
          <span class="brand-icon">🏺</span>
          <span class="brand-copy">
            <strong>{{ meta.site.title }}</strong>
            <small>实用网站导航</small>
          </span>
        </a>
        <nav class="top-actions">
          <button class="top-link" @click="goFeedback">反馈</button>
          <template v-if="user">
            <span class="user-name">{{ user.nickname || user.username }}</span>
            <button v-if="user.role === 'admin'" class="top-link" @click="openAdmin">后台管理</button>
            <button class="top-link" @click="logout">退出</button>
          </template>
          <template v-else>
            <button class="top-link" @click="openAuth('login')">登录</button>
            <button class="top-link" @click="openAuth('register')">注册</button>
          </template>
        </nav>
      </div>
    </header>

    <main class="shell" v-if="view === 'home'">
      <div class="backdrop" v-show="mobileOpen" @click="mobileOpen = false"></div>
      <aside class="sidebar" :class="{ collapsed: sidebarCollapsed }">
        <div class="sidebar-head">
          <strong>网站分类</strong>
          <button class="collapse-btn" type="button" @click="sidebarCollapsed = !sidebarCollapsed">
            {{ sidebarCollapsed ? '展开' : '收起' }}
          </button>
        </div>
        <nav class="category-nav">
          <a
            v-for="category in categories"
            :key="category.slug"
            class="category-link"
            :class="{ active: activeCategory === category.slug }"
            href="#"
            @click.prevent="chooseCategory(category.slug)"
          >
            <span class="category-icon">{{ category.icon }}</span>
            <span class="category-name">{{ category.name }}</span>
            <span class="category-count">{{ category.count }}</span>
          </a>
        </nav>
      </aside>

      <section class="main-content">
        <div class="heading-row">
          <div>
            <nav class="crumb" aria-label="面包屑">
              <a href="#" @click.prevent="goHome">首页</a>
              <span>/</span>
              <span aria-current="page">{{ currentCategoryName }}</span>
            </nav>
            <h1>{{ currentCategoryName }}</h1>
            <p class="lead">{{ sites.length }} 个实用网站，收录整理自公开工具合集。</p>
          </div>
          <form class="search" @submit.prevent="loadSites">
            <input v-model.trim="keyword" type="search" placeholder="搜索站点、简介或网址" aria-label="搜索站点">
            <button type="submit">搜索</button>
          </form>
        </div>

        <TransitionGroup name="card" tag="div" class="site-grid">
          <article class="site-card" v-for="site in sites" :key="site.id">
            <div class="site-top">
              <span class="site-icon">
                <img :src="favicon(site.url)" :alt="''" loading="lazy" @error="site.__iconFailed = true">
                <i v-if="site.__iconFailed" class="letter-icon">{{ firstLetter(site.name) }}</i>
              </span>
              <div class="site-info">
                <h3><a href="#" @click.prevent="openSite(site)">{{ site.name }}</a></h3>
                <span class="domain">{{ domain(site.url) }}</span>
              </div>
            </div>
            <p class="site-desc">{{ site.desc }}</p>
            <div class="extra-links" v-if="extraLinks(site).length">
              <a
                v-for="(link, index) in extraLinks(site)"
                :key="link.url + index"
                href="#"
                @click.prevent="openSite(site, index)"
              >{{ link.label || '备用地址' }}</a>
            </div>
            <div class="card-foot">
              <button class="like-btn" type="button" :class="{ liked: likedSites[site.id] }" @click="likeSite(site)">
                ♥ <span>{{ site.like_count || 0 }}</span>
              </button>
              <button class="visit-btn" type="button" @click="openSite(site)">
                访问
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 17 17 7M9 7h8v8"/></svg>
              </button>
            </div>
          </article>
        </TransitionGroup>
        <p class="empty" v-if="!loading && sites.length === 0">没有找到匹配站点</p>
      </section>
    </main>

    <main class="auth-page" v-else-if="view === 'login' || view === 'register'">
      <form class="panel-form" @submit.prevent="submitAuth">
        <h1>{{ view === 'login' ? '登录账号' : '创建账号' }}</h1>
        <p class="msg error" v-if="authError">{{ authError }}</p>
        <label>用户名<input v-model.trim="authForm.username" required minlength="3" maxlength="32"></label>
        <label v-if="view === 'register'">昵称<input v-model.trim="authForm.nickname" maxlength="60"></label>
        <label v-if="view === 'register'">邮箱<input v-model.trim="authForm.email" type="email"></label>
        <label>密码<input v-model="authForm.password" type="password" required minlength="6"></label>
        <button class="primary" type="submit">{{ view === 'login' ? '登录' : '注册并登录' }}</button>
        <p class="switch-line">
          <template v-if="view === 'login'">
            没有账号？<a href="#" @click.prevent="openAuth('register')">立即注册</a>
          </template>
          <template v-else>
            已有账号？<a href="#" @click.prevent="openAuth('login')">直接登录</a>
          </template>
        </p>
        <button class="text-btn" type="button" @click="goHome">返回首页</button>
      </form>
    </main>

    <main class="feedback-page" v-else-if="view === 'feedback'">
      <div class="feedback-wrap">
        <section class="panel-form">
          <h1>用户反馈</h1>
          <p class="note" v-if="!user">登录后可提交反馈并跟踪处理状态。</p>
          <p class="msg success" v-if="feedbackMsg">{{ feedbackMsg }}</p>
          <p class="msg error" v-if="feedbackError">{{ feedbackError }}</p>
          <template v-if="user">
            <label>类型
              <select v-model="feedbackForm.type">
                <option value="broken">链接失效</option>
                <option value="suggestion">功能建议</option>
                <option value="other">其他</option>
              </select>
            </label>
            <label>标题<input v-model.trim="feedbackForm.title" maxlength="200"></label>
            <label>相关网址<input v-model.trim="feedbackForm.siteUrl" type="url"></label>
            <label>反馈内容<textarea v-model.trim="feedbackForm.content" rows="5" required></textarea></label>
            <button class="primary" @click.prevent="submitFeedback">提交反馈</button>
          </template>
          <button class="text-btn" v-if="!user" @click="openAuth('login')">去登录</button>
          <button class="text-btn" @click="goHome">返回首页</button>
        </section>

        <section class="feedback-list" v-if="user">
          <h2>我的反馈记录</h2>
          <div class="feedback-row" v-for="item in feedbackList" :key="item.id">
            <div class="feedback-title">
              <strong>{{ item.title || '未命名反馈' }}</strong>
              <span class="badge">{{ statusLabel(item.status) }}</span>
            </div>
            <p>{{ item.content }}</p>
            <small v-if="item.admin_note">后台回复：{{ item.admin_note }}</small>
            <small>{{ item.created_at }}</small>
          </div>
          <p class="empty" v-if="!feedbackList.length">暂无反馈记录</p>
        </section>
      </div>
    </main>

    <main class="admin-page" v-else-if="view === 'admin'">
      <aside class="admin-side">
        <h2>后台管理</h2>
        <nav>
          <button :class="{ active: adminTab === 'dashboard' }" @click="loadAdminTab('dashboard')">数据概览</button>
          <button :class="{ active: adminTab === 'sites' }" @click="loadAdminTab('sites')">站点管理</button>
          <button :class="{ active: adminTab === 'users' }" @click="loadAdminTab('users')">用户管理</button>
          <button :class="{ active: adminTab === 'feedback' }" @click="loadAdminTab('feedback')">反馈工单</button>
          <button :class="{ active: adminTab === 'analytics' }" @click="loadAdminTab('analytics')">访问统计</button>
        </nav>
        <button class="text-btn" @click="goHome">返回前台</button>
      </aside>

      <section class="admin-main">
        <p class="msg error" v-if="adminError">{{ adminError }}</p>

        <div v-if="adminTab === 'dashboard' && stats">
          <div class="stat-grid">
            <div class="stat"><span>站点</span><strong>{{ stats.counts.sites }}</strong></div>
            <div class="stat"><span>用户</span><strong>{{ stats.counts.users }}</strong></div>
            <div class="stat"><span>访问</span><strong>{{ stats.counts.visits }}</strong></div>
            <div class="stat"><span>点击</span><strong>{{ stats.counts.clicks }}</strong></div>
            <div class="stat"><span>待处理工单</span><strong>{{ stats.counts.pending }}</strong></div>
          </div>
          <h2>最新反馈</h2>
          <table class="table">
            <tr><th>编号</th><th>用户</th><th>标题</th><th>状态</th><th>时间</th></tr>
            <tr v-for="item in stats.recentFeedback" :key="item.id">
              <td>#{{ item.id }}</td><td>{{ item.username || '用户' }}</td><td>{{ item.title || '未命名' }}</td>
              <td><span class="badge">{{ statusLabel(item.status) }}</span></td><td>{{ item.created_at }}</td>
            </tr>
          </table>
        </div>

        <div v-if="adminTab === 'sites'">
          <div class="toolbar">
            <h2>站点管理（{{ adminSites.length }}）</h2>
            <button class="primary" @click="newSiteForm">新增站点</button>
          </div>
          <form class="panel-form compact" v-if="siteForm" @submit.prevent="saveSite">
            <h3>{{ siteForm.id ? '编辑站点' : '新增站点' }}</h3>
            <label>站点名称<input v-model.trim="siteForm.name" required></label>
            <label>分类
              <select v-model="siteForm.category">
                <option v-for="c in categories" :key="c.slug" :value="c.slug">{{ c.name }}</option>
              </select>
            </label>
            <label>访问地址<input v-model.trim="siteForm.url" type="url" required></label>
            <label>简介<textarea v-model.trim="siteForm.desc" rows="3"></textarea></label>
            <label>备用链接（每行：标签|网址）<textarea v-model="siteForm.linksText" rows="4"></textarea></label>
            <label class="checkbox"><input type="checkbox" v-model="siteForm.isActive"> 前台显示</label>
            <div class="btn-row">
              <button class="primary" type="submit">保存</button>
              <button type="button" class="text-btn" @click="siteForm = null">取消</button>
            </div>
          </form>
          <table class="table">
            <tr><th>ID</th><th>站点</th><th>分类</th><th>状态</th><th>操作</th></tr>
            <tr v-for="site in adminSites" :key="site.id">
              <td>{{ site.id }}</td>
              <td><strong>{{ site.name }}</strong><br><small>{{ site.url }}</small></td>
              <td>{{ site.category }}</td>
              <td>{{ site.is_active ? '显示' : '隐藏' }}</td>
              <td class="row-actions">
                <button @click="editSite(site)">编辑</button>
                <button class="danger" @click="deleteSite(site.id)">删除</button>
              </td>
            </tr>
          </table>
        </div>

        <div v-if="adminTab === 'users'">
          <div class="toolbar"><h2>用户管理</h2><button class="primary" @click="newUserForm">新增用户</button></div>
          <form class="panel-form compact" v-if="userForm" @submit.prevent="saveUser">
            <h3>{{ userForm.id ? '编辑用户' : '新增用户' }}</h3>
            <label>用户名<input v-model.trim="userForm.username" required></label>
            <label>昵称<input v-model.trim="userForm.nickname"></label>
            <label>邮箱<input v-model.trim="userForm.email" type="email"></label>
            <label>密码<input v-model="userForm.password" type="password" :required="!userForm.id"></label>
            <label>角色
              <select v-model="userForm.role"><option value="user">普通用户</option><option value="admin">管理员</option></select>
            </label>
            <label class="checkbox"><input type="checkbox" v-model="userForm.isActive"> 启用</label>
            <div class="btn-row">
              <button class="primary" type="submit">保存</button>
              <button type="button" class="text-btn" @click="userForm = null">取消</button>
            </div>
          </form>
          <table class="table">
            <tr><th>ID</th><th>用户名</th><th>角色</th><th>状态</th><th>操作</th></tr>
            <tr v-for="item in adminUsers" :key="item.id">
              <td>{{ item.id }}</td><td>{{ item.username }}<br><small>{{ item.email }}</small></td>
              <td>{{ item.role === 'admin' ? '管理员' : '用户' }}</td>
              <td>{{ item.is_active ? '启用' : '停用' }}</td>
              <td class="row-actions">
                <button @click="editUser(item)">编辑</button>
                <button v-if="Number(item.id) !== Number(user.id)" class="danger" @click="deleteUser(item.id)">删除</button>
              </td>
            </tr>
          </table>
        </div>

        <div v-if="adminTab === 'feedback'">
          <h2>反馈工单</h2>
          <div class="feedback-row" v-for="item in adminFeedbacks" :key="item.id">
            <div class="feedback-title">
              <strong>#{{ item.id }} {{ item.title || '未命名反馈' }}</strong>
              <span class="badge">{{ statusLabel(item.status) }}</span>
            </div>
            <p>{{ item.content }}</p>
            <small>用户：{{ item.username || '游客' }} · {{ item.created_at }}</small>
            <form class="inline-form" @submit.prevent="updateFeedback(item)">
              <select v-model="item.status">
                <option value="pending">待处理</option>
                <option value="processing">处理中</option>
                <option value="resolved">已处理</option>
                <option value="closed">已关闭</option>
              </select>
              <input v-model="item.adminNote" placeholder="处理备注 / 回复">
              <button class="primary" type="submit">保存</button>
            </form>
          </div>
          <p class="empty" v-if="!adminFeedbacks.length">暂无工单</p>
        </div>

        <div v-if="adminTab === 'analytics' && analytics">
          <div class="toolbar"><h2>访问分析（最近 {{ analytics.days }} 天）</h2></div>
          <div class="stat-grid">
            <div class="stat"><span>总访问</span><strong>{{ analytics.totals.visits }}</strong></div>
            <div class="stat"><span>独立访客</span><strong>{{ analytics.totals.unique }}</strong></div>
            <div class="stat"><span>站点点击</span><strong>{{ analytics.totals.clicks }}</strong></div>
          </div>
          <h2>访问量趋势</h2>
          <div class="chart">
            <div class="bar" v-for="row in analytics.trend" :key="row.day">
              <span>{{ row.day }}</span>
              <i :style="{ width: barWidth(row.visits, maxTrend) + '%' }"></i>
              <b>{{ row.visits }}</b>
            </div>
          </div>
          <h2>热门站点点击排行</h2>
          <table class="table">
            <tr><th>站点</th><th>分类</th><th>点击</th></tr>
            <tr v-for="row in analytics.topSites" :key="row.id">
              <td><a :href="row.url" target="_blank" rel="noopener noreferrer">{{ row.name }}</a></td>
              <td>{{ row.category_name || row.category }}</td><td>{{ row.clicks }}</td>
            </tr>
          </table>
          <h2>分类访问分布</h2>
          <table class="table">
            <tr><th>分类</th><th>访问</th></tr>
            <tr v-for="row in analytics.categories" :key="row.category"><td>{{ row.category }}</td><td>{{ row.visits }}</td></tr>
          </table>
        </div>
      </section>
    </main>

    <footer class="footer">
      <div class="friends">
        <a v-for="link in meta.friendLinks" :key="link.url" :href="link.url" target="_blank" rel="noopener noreferrer">{{ link.name }}</a>
      </div>
      <p>© 阿旻同学工具箱 · 数据整理自飞书文档</p>
      <a class="icp" href="https://beian.miit.gov.cn/" target="_blank" rel="noopener noreferrer">{{ meta.site.icp }}</a>
    </footer>
  </div>`;

  createApp({
    data() {
      return {
        view: 'home',
        loading: true,
        sidebarCollapsed: false,
        mobileOpen: false,
        user: null,
        meta: { site: { title: '阿旻同学 | 工具箱', icp: '' }, friendLinks: [] },
        categories: [],
        sites: [],
        activeCategory: 'all',
        keyword: '',
        likedSites: {},
        authForm: { username: '', password: '', nickname: '', email: '' },
        authError: '',
        feedbackForm: { type: 'broken', title: '', content: '', siteUrl: '' },
        feedbackList: [],
        feedbackMsg: '',
        feedbackError: '',
        adminTab: 'dashboard',
        adminError: '',
        stats: null,
        adminSites: [],
        adminUsers: [],
        adminFeedbacks: [],
        analytics: null,
        siteForm: null,
        userForm: null,
        visitedRecorded: false,
      };
    },
    computed: {
      currentCategoryName() {
        if (this.activeCategory === 'all') return '全部站点';
        const current = this.categories.find((item) => item.slug === this.activeCategory);
        return current ? current.name : '全部站点';
      },
      maxTrend() {
        return Math.max(1, ...(this.analytics?.trend || []).map((row) => Number(row.visits)));
      },
    },
    mounted() {
      this.init();
    },
    methods: {
      async init() {
        try {
          const meta = await request('/api/meta');
          this.meta = meta.data;
          document.title = this.meta.site.title;
          const categories = await request('/api/categories');
          this.categories = categories.data;
          const me = await request('/api/auth/me');
          this.user = me.user;
          await this.loadSites();
          if (!this.visitedRecorded) {
            this.visitedRecorded = true;
            request('/api/analytics/visit', {
              method: 'POST',
              body: JSON.stringify({ category: this.activeCategory, pageUrl: '/', referrer: document.referrer }),
            }).catch(() => {});
          }
        } catch (error) {
          console.error(error);
        } finally {
          this.loading = false;
        }
      },
      async loadSites() {
        this.loading = true;
        try {
          const params = new URLSearchParams({ category: this.activeCategory });
          if (this.keyword) params.set('q', this.keyword);
          const result = await request('/api/sites?' + params.toString());
          this.sites = result.data;
        } catch (error) {
          this.sites = [];
        } finally {
          this.loading = false;
        }
      },
      goHome() {
        this.view = 'home';
        this.adminError = '';
      },
      chooseCategory(slug) {
        this.activeCategory = slug;
        this.keyword = '';
        this.view = 'home';
        this.loadSites();
        request('/api/analytics/visit', {
          method: 'POST',
          body: JSON.stringify({ category: slug, pageUrl: '/?cat=' + slug }),
        }).catch(() => {});
      },
      toggleSidebar() {
        if (window.innerWidth <= 900) {
          this.mobileOpen = !this.mobileOpen;
        } else {
          this.sidebarCollapsed = !this.sidebarCollapsed;
        }
      },
      openAuth(mode) {
        this.view = mode;
        this.authError = '';
      },
      async submitAuth() {
        this.authError = '';
        try {
          const result = await request(this.view === 'login' ? '/api/auth/login' : '/api/auth/register', {
            method: 'POST',
            body: JSON.stringify(
              this.view === 'login'
                ? { username: this.authForm.username, password: this.authForm.password }
                : this.authForm
            ),
          });
          this.user = result.user;
          this.goHome();
        } catch (error) {
          this.authError = error.message;
        }
      },
      async logout() {
        await request('/api/auth/logout', { method: 'POST', body: '{}' }).catch(() => {});
        this.user = null;
        this.goHome();
      },
      async openAdmin() {
        if (!this.user) {
          this.openAuth('login');
          return;
        }
        if (this.user.role !== 'admin') {
          this.adminError = '当前账号没有后台权限';
          return;
        }
        this.view = 'admin';
        await this.loadAdminTab('dashboard');
      },
      async loadAdminTab(tab) {
        this.adminTab = tab;
        this.adminError = '';
        try {
          if (tab === 'dashboard') {
            const result = await request('/api/admin/stats');
            this.stats = result.data;
          } else if (tab === 'sites') {
            const result = await request('/api/admin/sites');
            this.adminSites = result.data;
          } else if (tab === 'users') {
            const result = await request('/api/admin/users');
            this.adminUsers = result.data;
          } else if (tab === 'feedback') {
            const result = await request('/api/admin/feedback');
            this.adminFeedbacks = result.data.map((item) => ({ ...item, adminNote: item.admin_note || '' }));
          } else if (tab === 'analytics') {
            const result = await request('/api/admin/analytics?days=14');
            this.analytics = result.data;
          }
        } catch (error) {
          this.adminError = error.message;
        }
      },
      async openSite(site, linkIndex) {
        const targetWindow = window.open('', '_blank', 'noopener');
        try {
          const result = await request('/api/analytics/click', {
            method: 'POST',
            body: JSON.stringify({ siteId: site.id, linkIndex: linkIndex == null ? null : linkIndex }),
          });
          if (targetWindow) targetWindow.location.href = result.url;
          else window.location.href = result.url;
        } catch (error) {
          if (targetWindow) targetWindow.close();
          alert(error.message);
        }
      },
      async likeSite(site) {
        if (this.likedSites[site.id]) return;
        try {
          const result = await request('/api/sites/like', {
            method: 'POST',
            body: JSON.stringify({ siteId: site.id }),
          });
          site.like_count = result.count;
          this.likedSites = { ...this.likedSites, [site.id]: true };
        } catch (error) {
          alert(error.message);
        }
      },
      async goFeedback() {
        this.view = 'feedback';
        this.feedbackMsg = '';
        this.feedbackError = '';
        if (!this.user) return;
        await this.loadFeedback();
      },
      async loadFeedback() {
        try {
          const result = await request('/api/feedback');
          this.feedbackList = result.data;
        } catch (error) {
          this.feedbackError = error.message;
        }
      },
      async submitFeedback() {
        this.feedbackError = '';
        try {
          await request('/api/feedback', {
            method: 'POST',
            body: JSON.stringify(this.feedbackForm),
          });
          this.feedbackMsg = '反馈已提交，后台处理后会更新状态';
          this.feedbackForm = { type: 'broken', title: '', content: '', siteUrl: '' };
          await this.loadFeedback();
        } catch (error) {
          this.feedbackError = error.message;
        }
      },
      newSiteForm() {
        this.siteForm = { id: '', name: '', category: this.categories[0]?.slug || 'tool', url: '', desc: '', linksText: '', isActive: true };
      },
      editSite(site) {
        const linksText = (site.links || [])
          .filter((link) => link.url !== site.url)
          .map((link) => `${link.label || ''}|${link.url}`)
          .join('\n');
        this.siteForm = {
          id: site.id,
          name: site.name,
          category: site.category,
          url: site.url,
          desc: site.desc,
          linksText,
          isActive: Number(site.is_active) === 1,
        };
      },
      async saveSite() {
        const links = this.siteForm.linksText
          .split('\n')
          .map((line) => line.trim())
          .filter(Boolean)
          .map((line) => {
            const parts = line.split('|');
            return parts.length > 1
              ? { label: parts[0].trim(), url: parts.slice(1).join('|').trim() }
              : { label: '', url: parts[0].trim() };
          })
          .filter((item) => /^https?:\/\//i.test(item.url));
        const payload = {
          name: this.siteForm.name,
          category: this.siteForm.category,
          url: this.siteForm.url,
          desc: this.siteForm.desc,
          links,
          sort_order: 0,
          is_active: this.siteForm.isActive,
        };
        try {
          if (this.siteForm.id) {
            await request('/api/admin/sites/' + encodeURIComponent(this.siteForm.id), {
              method: 'PUT',
              body: JSON.stringify(payload),
            });
          } else {
            await request('/api/admin/sites', {
              method: 'POST',
              body: JSON.stringify(payload),
            });
          }
          this.siteForm = null;
          await this.loadAdminTab('sites');
          await this.loadSites();
          const cats = await request('/api/categories');
          this.categories = cats.data;
        } catch (error) {
          alert(error.message);
        }
      },
      async deleteSite(id) {
        if (!confirm('确认删除该站点？')) return;
        try {
          await request('/api/admin/sites/' + encodeURIComponent(id), { method: 'DELETE' });
          await this.loadAdminTab('sites');
          await this.loadSites();
        } catch (error) {
          alert(error.message);
        }
      },
      newUserForm() {
        this.userForm = { id: 0, username: '', nickname: '', email: '', password: '', role: 'user', isActive: true };
      },
      editUser(item) {
        this.userForm = {
          id: item.id,
          username: item.username,
          nickname: item.nickname,
          email: item.email,
          password: '',
          role: item.role,
          isActive: Number(item.is_active) === 1,
        };
      },
      async saveUser() {
        const payload = {
          username: this.userForm.username,
          nickname: this.userForm.nickname,
          email: this.userForm.email,
          password: this.userForm.password,
          role: this.userForm.role,
          is_active: this.userForm.isActive,
        };
        try {
          if (this.userForm.id) {
            await request('/api/admin/users/' + this.userForm.id, {
              method: 'PUT',
              body: JSON.stringify(payload),
            });
          } else {
            await request('/api/admin/users', { method: 'POST', body: JSON.stringify(payload) });
          }
          this.userForm = null;
          await this.loadAdminTab('users');
        } catch (error) {
          alert(error.message);
        }
      },
      async deleteUser(id) {
        if (!confirm('确认删除该用户？')) return;
        try {
          await request('/api/admin/users/' + id, { method: 'DELETE' });
          await this.loadAdminTab('users');
        } catch (error) {
          alert(error.message);
        }
      },
      async updateFeedback(item) {
        try {
          await request('/api/admin/feedback/' + item.id, {
            method: 'PUT',
            body: JSON.stringify({ status: item.status, adminNote: item.adminNote }),
          });
          await this.loadAdminTab('feedback');
        } catch (error) {
          alert(error.message);
        }
      },
      favicon(url) {
        try {
          const host = new URL(url).hostname;
          return `https://icons.duckduckgo.com/ip3/${host}.ico`;
        } catch (error) {
          return '';
        }
      },
      domain(url) {
        try {
          return new URL(url).hostname;
        } catch (error) {
          return url;
        }
      },
      firstLetter(name) {
        return Array.from(String(name || ''))[0] || '#';
      },
      extraLinks(site) {
        return (site.links || []).filter((link) => link.url !== site.url);
      },
      statusLabel(status) {
        return STATUS_LABELS[status] || status;
      },
      typeLabel(type) {
        return TYPE_LABELS[type] || type;
      },
      barWidth(value, max) {
        return Math.max(2, Math.round((Number(value) / Number(max)) * 100));
      },
    },
}).mount('#app');
