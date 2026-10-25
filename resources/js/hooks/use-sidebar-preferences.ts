import { useSyncExternalStore } from 'react';
import {
    SIDEBAR_MAX_WIDTH,
    SIDEBAR_MIN_WIDTH,
} from '@/components/ui/fluid/sidebar';

/** Preferências da sidebar do shell no localStorage: recolhida e largura do rail. */
const SIDEBAR_COLLAPSED_KEY = 'sidebar:collapsed';
const SIDEBAR_WIDTH_KEY = 'sidebar:width';
const SIDEBAR_CLOSED_GROUPS_KEY = 'sidebar:closed-groups';
export const DEFAULT_SIDEBAR_WIDTH = '15rem';

let fallbackCollapsed = false;
let fallbackWidth = DEFAULT_SIDEBAR_WIDTH;
let fallbackClosedGroups = '[]';
let sidebarStorageUnavailable = false;
const sidebarListeners = new Set<() => void>();

function notify(): void {
    sidebarListeners.forEach((listener) => listener());
}

function subscribeSidebar(listener: () => void): () => void {
    sidebarListeners.add(listener);
    window.addEventListener('storage', listener);

    return () => {
        sidebarListeners.delete(listener);
        window.removeEventListener('storage', listener);
    };
}

function getCollapsedSnapshot(): boolean {
    if (sidebarStorageUnavailable) {
        return fallbackCollapsed;
    }

    try {
        return window.localStorage.getItem(SIDEBAR_COLLAPSED_KEY) === 'true';
    } catch {
        sidebarStorageUnavailable = true;

        return fallbackCollapsed;
    }
}

function isRailWidth(px: number): boolean {
    return px >= SIDEBAR_MIN_WIDTH && px <= SIDEBAR_MAX_WIDTH;
}

/** Largura escolhida no arraste do rail; valor ausente ou fora do limite volta ao padrão. */
function getWidthSnapshot(): string {
    if (sidebarStorageUnavailable) {
        return fallbackWidth;
    }

    try {
        const px = Number(window.localStorage.getItem(SIDEBAR_WIDTH_KEY));

        return isRailWidth(px) ? `${px}px` : DEFAULT_SIDEBAR_WIDTH;
    } catch {
        sidebarStorageUnavailable = true;

        return fallbackWidth;
    }
}

/** JSON cru da lista de grupos fechados: string mantém o snapshot estável. */
function getClosedGroupsSnapshot(): string {
    if (sidebarStorageUnavailable) {
        return fallbackClosedGroups;
    }

    try {
        return window.localStorage.getItem(SIDEBAR_CLOSED_GROUPS_KEY) ?? '[]';
    } catch {
        sidebarStorageUnavailable = true;

        return fallbackClosedGroups;
    }
}

function parseClosedGroups(raw: string): string[] {
    try {
        const parsed: unknown = JSON.parse(raw);

        return Array.isArray(parsed)
            ? parsed.filter((title) => typeof title === 'string')
            : [];
    } catch {
        return [];
    }
}

export function setSidebarGroupOpen(title: string, open: boolean): void {
    const closed = parseClosedGroups(getClosedGroupsSnapshot()).filter(
        (closedTitle) => closedTitle !== title,
    );

    if (!open) {
        closed.push(title);
    }

    fallbackClosedGroups = JSON.stringify(closed);

    try {
        window.localStorage.setItem(
            SIDEBAR_CLOSED_GROUPS_KEY,
            fallbackClosedGroups,
        );
    } catch {
        sidebarStorageUnavailable = true;
    }

    notify();
}

export function setSidebarCollapsed(collapsed: boolean): void {
    fallbackCollapsed = collapsed;

    try {
        window.localStorage.setItem(SIDEBAR_COLLAPSED_KEY, String(collapsed));
    } catch {
        sidebarStorageUnavailable = true;
        // A preferência continua funcional na sessão atual.
    }

    notify();
}

export function setSidebarWidth(width: string): void {
    const px = parseFloat(width);

    // O Fluid publica a largura arrastada em px; o padrão em rem não é salvo.
    if (!width.endsWith('px') || !isRailWidth(px)) {
        return;
    }

    fallbackWidth = width;

    try {
        window.localStorage.setItem(SIDEBAR_WIDTH_KEY, String(px));
    } catch {
        sidebarStorageUnavailable = true;
    }

    notify();
}

/** Volta a sidebar ao padrão: expandida, na largura original e com os grupos abertos. */
export function resetSidebarPreferences(): void {
    fallbackCollapsed = false;
    fallbackWidth = DEFAULT_SIDEBAR_WIDTH;
    fallbackClosedGroups = '[]';

    try {
        window.localStorage.setItem(SIDEBAR_COLLAPSED_KEY, 'false');
        window.localStorage.removeItem(SIDEBAR_WIDTH_KEY);
        window.localStorage.removeItem(SIDEBAR_CLOSED_GROUPS_KEY);
    } catch {
        sidebarStorageUnavailable = true;
    }

    notify();
}

export function useSidebarPreferences(): { collapsed: boolean; width: string } {
    const collapsed = useSyncExternalStore(
        subscribeSidebar,
        getCollapsedSnapshot,
        () => false,
    );
    const width = useSyncExternalStore(
        subscribeSidebar,
        getWidthSnapshot,
        () => DEFAULT_SIDEBAR_WIDTH,
    );

    return { collapsed, width };
}

/** Grupo de seção da sidebar aberto, salvo pelo título (aberto por padrão). */
export function useSidebarGroupOpen(title: string): boolean {
    const closedGroups = useSyncExternalStore(
        subscribeSidebar,
        getClosedGroupsSnapshot,
        () => '[]',
    );

    return !parseClosedGroups(closedGroups).includes(title);
}
