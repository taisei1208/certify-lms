let csrfCookieInitialized = false;

/**
 * Cookieから指定した値を取得する。
 */
function readCookie(name) {
    const prefix = `${name}=`;

    const cookie = document.cookie.split('; ').find((item) => item.startsWith(prefix));

    if (!cookie) {
        return null;
    }

    return decodeURIComponent(
        cookie.substring(prefix.length),
    );
}

/**
 * SanctumのCSRF Cookieを取得する。
 */
async function ensureCsrfCookie() {
    if (csrfCookieInitialized && readCookie('XSRF-TOKEN')) {
        return;
    }

    const response = await fetch(
        '/sanctum/csrf-cookie',
        {
            method: 'GET',
            credentials: 'same-origin',
            headers: {Accept: 'application/json'}
        }
    );

    if (!response.ok) {
        throw new Error('CSRF Cookieの取得に失敗しました。');
    }

    csrfCookieInitialized = true;
}

/**
 * JSON形式のPOSTリクエストを送信する。
 */
async function postJson(url) {
    await ensureCsrfCookie();

    const xsrfToken = readCookie('XSRF-TOKEN');

    if (!xsrfToken) {
        throw new Error('CSRFトークンを取得できませんでした。');
    }

    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-XSRF-TOKEN': xsrfToken,
        },
    });

    if (!response.ok) {
        throw new Error(`APIの呼び出しに失敗しました: ${response.status}`);
    }

    return response.json();
}

export function initNotificationPopover() {
    const root = document.querySelector(
        '[data-notification-popover-root]'
    );

    if (!root) {
        return;
    }

    const trigger = root.querySelector(
        '[data-notification-popover-trigger]'
    );

    const panel = root.querySelector(
        '[data-notification-popover-panel]'
    );

    const tabs = Array.from(root.querySelectorAll(
        '[data-notification-popover-tab]'
    ));

    const loading = root.querySelector(
        '[data-notification-popover-loading]'
    );

    const empty = root.querySelector(
        '[data-notification-popover-empty]'
    );

    const items = root.querySelector(
        '[data-notification-popover-items]'
    );

    const rowTemplate = root.querySelector(
        '[data-notification-popover-row-template]',
    );

    const unreadCount = root.querySelector(
        '[data-notification-popover-unread-count]',
    );

    const badge = root.querySelector(
        '[data-notification-popover-badge]',
    );

    const markAllButton = root.querySelector(
        '[data-notification-popover-mark-all]',
    );

    if (!trigger || !panel || !loading || !empty || !items || !rowTemplate || !unreadCount || !badge || !markAllButton) {
        return;
    }

    let isOpen = false;
    let isLoading = false;
    let activeTab = 'all';

    /**
     * ポップオーバーを開く。
     */
    function open() {
        isOpen = true;

        trigger.setAttribute('aria-expanded', 'true');

        panel.style.display = 'flex';
        panel.classList.remove('hidden');

        window.requestAnimationFrame(() => {
            panel.classList.remove(
                'opacity-0',
                '-translate-y-1'
            );

            panel.classList.add(
                'opacity-100',
                'translate-y-0'
            );
        });

        loadNotifications();
    }

    /**
     * ポップオーバーを閉じる。
     */
    function close() {
        isOpen = false;

        trigger.setAttribute('aria-expanded', 'false');

        panel.classList.remove(
                'opacity-100',
                'translate-y-0'
        );

        panel.classList.add(
                'opacity-0',
                '-translate-y-1'
            );

        window.setTimeout(() => {
            if (isOpen) {
                return;
            }

            panel.classList.add('hidden');
            panel.style.display = 'none';

        }, 150);
    }

    /**
     * ベルを押すたびに開閉を切り替える。
     */
    trigger.addEventListener('click', () => {
        if (isOpen) {
            close();
            return;
        }

        open();
    })

    /**
     * APIから返された通知1件をHTMLへ変換する。
     */
    function createNotificationRow(notification) {
        const fragment = rowTemplate.content.cloneNode(true);

        const row = fragment.querySelector(
            '[data-notification-popover-row]'
        );

        const dot = row.querySelector(
            '[data-notification-popover-row-dot]'
        );

        const title = row.querySelector(
            '[data-notification-popover-row-title]'
        );

        const message = row.querySelector(
            '[data-notification-popover-row-message]'
        );

        const time = row.querySelector(
            '[data-notification-popover-row-time]'
        );

        title.textContent = notification.title;
        message.textContent = notification.message;
        time.textContent = notification.created_at_human ?? '';

        row.href = notification.url;

        const isUnread = !notification.is_read;

        row.dataset.unread = isUnread
            ? 'true'
            : 'false';

        if (isUnread) {
            row.classList.add('bg-primary-50/30');
        } else {
            dot.classList.add('invisible');
        }

        row.addEventListener('click', async (event) => {
            event.preventDefault();

            if (!isUnread) {
                window.location.assign(notification.url);
                return;
            }

            row.classList.add(
                'pointer-events-none',
                'opacity-60',
            );

            try {
                const result = await postJson(
                    `/api/v1/notifications/${notification.id}/read`,
                );

                updateUnreadCount(result.unread_count ?? 0);

                window.location.assign(
                    result.url ?? notification.url,
                );
            } catch (error) {
                console.error(error);

                row.classList.remove(
                    'pointer-events-none',
                    'opacity-60',
                );

                window.alert('通知の既読化に失敗しました。');
            }
        });

        return fragment;
    }

    /**
     * 通知一覧を描画する。
     */
    function renderNotifications(notifications) {
        items.replaceChildren();

        if (notifications.length === 0) {
            empty.textContent = '通知がありません。'
            empty.classList.remove('hidden');

            return;
        }

        empty.classList.add('hidden');

        notifications.forEach((notification) => {
            items.appendChild(createNotificationRow(notification));
        });
    }

    /**
     * API通信中の表示を切り替える。
     */
    function setLoading(value) {
        isLoading = value;

        loading.classList.toggle('hidden', !value);

        tabs.forEach((tab) => {
            tab.disabled = value;
        });
    }

    /**
     * 現在選択中のタブに対応する通知を取得する。
     */
    async function loadNotifications() {
        if (isLoading) {
            return;
        }

        setLoading(true);

        empty.classList.add('hidden');
        items.replaceChildren();

        try {
            const query = new URLSearchParams({
                tab: activeTab
            });

            const response = await fetch(
                `/api/v1/notifications?${query.toString()}`,
                {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' }
                }
            );

            if (!response.ok) {
                throw new Error(
                    `通知の取得に失敗しました: ${response.status}`
                );
            }

            const result = await response.json();

            renderNotifications(result.data ?? []);

            updateUnreadCount(
                result.meta?.unread_count ?? 0
            );
        } catch(error) {
            console.error(error);

            empty.textContent = '通知の読み込みに失敗しました。'
            empty.classList.remove('hidden');
        } finally {
            setLoading(false);
        }
    }

    /**
     * 選択中タブの見た目を更新する。
     */
    function updateTabs() {
        tabs.forEach((tab) => {
            const selected = tab.dataset.notificationPopoverTab === activeTab;

            tab.setAttribute(
                'aria-selected', selected ? 'true' : 'false'
            );
        });
    }

    /**
     * 全件・未読タブを切り替える。
     */
    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            const selectedTab = tab.dataset.notificationPopoverTab;

            if (selectedTab !== 'all' && selectedTab !== 'unread') {
                return;
            }

            if (selectedTab === activeTab) {
                return;
            }

            activeTab = selectedTab;

            updateTabs();
            loadNotifications()
        })
    })

    /**
     * 未読件数表示を更新する。
     */
    function updateUnreadCount(count) {
        const normalizedCount = Math.max(
            0,
            Number(count) || 0,
        );

        unreadCount.textContent = String(
            normalizedCount,
        );

        badge.textContent = normalizedCount > 99
            ? '99+'
            : String(normalizedCount);

        badge.classList.toggle(
            'hidden',
            normalizedCount === 0,
        );

        trigger.setAttribute(
            'aria-label',
            `通知 (${normalizedCount} 件未読)`,
        );

        markAllButton.disabled = normalizedCount === 0;

        markAllButton.classList.toggle(
            'opacity-50',
            normalizedCount === 0,
        );
    }

    markAllButton.addEventListener(
        'click',
        async () => {
            if (isLoading || markAllButton.disabled) {
                return;
            }

            markAllButton.disabled = true;

            try {
                const result = await postJson(
                    '/api/v1/notifications/read-all',
                );

                updateUnreadCount(result.unread_count ?? 0);

                await loadNotifications();
            } catch (error) {
                console.error(error);

                markAllButton.disabled = false;

                window.alert('通知の一括既読化に失敗しました。');
            }
        }
    );
}
