import {
    Activity,
    BellRing,
    BookOpen,
    Building2,
    Clock,
    Contact,
    Download,
    Eye,
    FolderGit2,
    History,
    KeyRound,
    LayoutGrid,
    Layers,
    ListMusic,
    MessageSquare,
    Mic2,
    PencilLine,
    Paperclip,
    PlugZap,
    Puzzle,
    Search,
    Shield,
    ShieldCheck,
    TableProperties,
    Users,
    Wrench,
    Zap,
    Archive,
    Mail,
    DatabaseZap,
    Bell,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

/**
 * Mapa de nomes de ícones Lucide (string) para os componentes correspondentes.
 * Adicione aqui qualquer ícone referenciado em config/navigation.php.
 */
const NAV_ICONS: Record<string, LucideIcon> = {
    Activity,
    BellRing,
    BookOpen,
    Building2,
    Clock,
    Contact,
    Download,
    Eye,
    FolderGit2,
    History,
    KeyRound,
    LayoutGrid,
    Layers,
    ListMusic,
    MessageSquare,
    Mic2,
    PencilLine,
    Paperclip,
    PlugZap,
    Puzzle,
    Search,
    Shield,
    ShieldCheck,
    TableProperties,
    Users,
    Wrench,
    Zap,
    Archive,
    Mail,
    DatabaseZap,
    Bell,
};

export function resolveIcon(name?: string | null): LucideIcon | null {
    if (!name) {
        return null;
    }

    return NAV_ICONS[name] ?? null;
}
