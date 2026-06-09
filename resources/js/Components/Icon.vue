<script setup>
import { computed } from 'vue';
// Importação NOMEADA (não `import *`) — preserva tree-shaking do Vite/Rollup,
// só os ícones listados aqui entram no bundle final.
import {
    LayoutDashboard, KanbanSquare, Layers, CircleCheckBig, FolderKanban,
    Clock, Rocket, Settings, Users, Bot, Bell, BellOff,
    RotateCcw, Ban, Pencil, Trash2, Paperclip, Image, Link as LinkIcon,
    TriangleAlert, Check, X, Minus, Plus, Send, Search, ChevronDown,
    FileText, MessageSquare, User, UserCircle, LogOut,
    Bold, Italic, Heading, List, Code, PartyPopper,
    CircleDot, CircleDashed, Eye, Download, Copy, RefreshCw,
    ShieldAlert, KeyRound, Mail, ListTodo, BarChart3, Clipboard,
    CircleAlert, CircleX, Hourglass, FlaskConical, Boxes,
} from 'lucide-vue-next';

// Mapa nome-semântico → componente Lucide. Usar nomes curtos/estáveis no
// template (`<Icon name="rocket" />`) isola o resto do código do nome exato
// do ícone — se trocar de ícone, muda só aqui.
const ICONS = {
    // Navegação / sidebar
    dashboard: LayoutDashboard,
    board: KanbanSquare,
    backlog: Layers,
    completed: CircleCheckBig,
    projects: FolderKanban,
    'time-entries': Clock,
    deploys: Rocket,
    admin: Settings,
    users: Users,
    bot: Bot,
    reports: BarChart3,

    // Notificações / header
    bell: Bell,
    'bell-off': BellOff,
    user: User,
    'user-circle': UserCircle,
    logout: LogOut,
    chevron: ChevronDown,

    // Ações de card
    reopen: RotateCcw,
    block: Ban,
    edit: Pencil,
    trash: Trash2,
    attach: Paperclip,
    image: Image,
    link: LinkIcon,
    check: Check,
    x: X,
    minus: Minus,
    plus: Plus,
    send: Send,
    search: Search,
    eye: Eye,
    download: Download,
    copy: Copy,
    refresh: RefreshCw,

    // Tabs / conteúdo
    details: FileText,
    subtasks: ListTodo,
    comments: MessageSquare,
    list: List,

    // Editor markdown toolbar
    bold: Bold,
    italic: Italic,
    heading: Heading,
    code: Code,

    // Status / estados
    warning: TriangleAlert,
    'shield-alert': ShieldAlert,
    party: PartyPopper,
    'circle-dot': CircleDot,
    'circle-dashed': CircleDashed,
    'circle-alert': CircleAlert,
    'circle-x': CircleX,
    hourglass: Hourglass,
    flask: FlaskConical,
    boxes: Boxes,

    // Auth / segurança
    key: KeyRound,
    mail: Mail,
    clipboard: Clipboard,
};

const props = defineProps({
    name: { type: String, required: true },
    size: { type: [Number, String], default: 20 },
    strokeWidth: { type: [Number, String], default: 2 },
});

const component = computed(() => ICONS[props.name] || ICONS['circle-dot']);
</script>

<template>
    <component :is="component" :size="Number(size)" :stroke-width="Number(strokeWidth)" />
</template>
