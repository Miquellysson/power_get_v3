// assets/admin.js — helpers do shell moderno do admin
(function () {
  const storageKey = 'admin.theme';
  const root = document.documentElement;
  const appContainer = document.querySelector('.app-container');
  const themeToggle = document.querySelector('[data-theme-toggle]');
  const themeIcon = document.querySelector('[data-theme-icon]');
  const sidebarToggleButtons = document.querySelectorAll('[data-sidebar-toggle]');
  const sidebarMobileTrigger = document.querySelector('[data-sidebar-mobile-trigger]');
  const sidebarBackdrop = document.querySelector('[data-sidebar-backdrop]');
  const mq = window.matchMedia('(max-width: 1024px)');

  const setTheme = (value) => {
    const theme = value === 'dark' ? 'dark' : 'light';
    root.setAttribute('data-theme', theme);
    try {
      localStorage.setItem(storageKey, theme);
    } catch (err) {
      // storage indisponível — seguimos sem persistir
    }
    if (themeIcon) {
      themeIcon.classList.remove('fa-moon', 'fa-sun');
      themeIcon.classList.add(theme === 'dark' ? 'fa-sun' : 'fa-moon');
    }
    if (themeToggle) {
      themeToggle.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
    }
  };

  const toggleTheme = () => {
    const current = root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    setTheme(current === 'dark' ? 'light' : 'dark');
  };

  const setSidebarState = (state) => {
    if (!appContainer) {
      return;
    }
    const value = state === 'collapsed' ? 'collapsed' : 'expanded';
    appContainer.setAttribute('data-sidebar-state', value);
  };

  const toggleSidebar = () => {
    if (!appContainer) {
      return;
    }
    const current = appContainer.getAttribute('data-sidebar-state') === 'collapsed' ? 'collapsed' : 'expanded';
    setSidebarState(current === 'collapsed' ? 'expanded' : 'collapsed');
  };

  const syncSidebarWithViewport = (event) => {
    if (!appContainer) {
      return;
    }
    if (event.matches) {
      setSidebarState('collapsed');
    } else {
      setSidebarState('expanded');
    }
  };

  const init = () => {
    // Tema
    try {
      const stored = localStorage.getItem(storageKey);
      if (stored === 'dark' || stored === 'light') {
        setTheme(stored);
      } else {
        setTheme(root.getAttribute('data-theme') || 'light');
      }
    } catch (err) {
      setTheme(root.getAttribute('data-theme') || 'light');
    }
    if (themeToggle) {
      themeToggle.addEventListener('click', toggleTheme);
    }

    // Sidebar
    sidebarToggleButtons.forEach((btn) => {
      btn.addEventListener('click', toggleSidebar);
    });
    if (sidebarMobileTrigger) {
      sidebarMobileTrigger.addEventListener('click', () => setSidebarState('expanded'));
    }
    if (sidebarBackdrop) {
      sidebarBackdrop.addEventListener('click', () => setSidebarState('collapsed'));
    }
    if (typeof mq.addEventListener === 'function') {
      mq.addEventListener('change', syncSidebarWithViewport);
    } else if (typeof mq.addListener === 'function') {
      mq.addListener(syncSidebarWithViewport);
    }
    syncSidebarWithViewport(mq);

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && mq.matches && appContainer && appContainer.getAttribute('data-sidebar-state') === 'expanded') {
        setSidebarState('collapsed');
      }
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

document.addEventListener('click', (event) => {
  const btn = event.target.closest('[data-copy]');
  if (!btn) {
    return;
  }
  const text = btn.getAttribute('data-copy') || '';
  navigator.clipboard.writeText(text).then(() => {
    btn.classList.add('copied');
    setTimeout(() => btn.classList.remove('copied'), 1200);
  }).catch(() => {});
});
