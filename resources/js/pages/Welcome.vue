<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    BadgeCheck,
    Clock,
    Copy,
    FileSpreadsheet,
    Gamepad2,
    Hourglass,
    LockKeyhole,
    MonitorPause,
    MousePointer2,
    Recycle,
    ServerCog,
    Upload,
    UserRoundSearch,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';

defineProps<{
    canRegister?: boolean;
}>();

const page = usePage();
const appName = page.props.name;
const dashboardUrl = computed(() =>
    page.props.currentTeam ? dashboard(page.props.currentTeam.slug).url : '/',
);

const steps = [
    {
        icon: UserRoundSearch,
        title: 'Pilih karyawan',
        text: 'Isi nama karyawan dan tanggal kerja yang ingin dicek.',
    },
    {
        icon: Upload,
        title: 'Unggah screenshot',
        text: 'Tarik 100–1000 screenshot JPG/PNG dari aplikasi pemantau sekaligus.',
    },
    {
        icon: BadgeCheck,
        title: 'Tinjau temuan',
        text: 'Lihat timeline, temuan, dan bukti gambarnya. Unduh laporan Excel atau PDF.',
    },
];

const detections = [
    {
        icon: MonitorPause,
        title: 'Layar diam',
        text: 'Layar hampir tidak berubah dari screenshot sebelumnya.',
    },
    {
        icon: MousePointer2,
        title: 'Mouse jiggler',
        text: 'Hanya kursor yang bergerak, berulang kali berturut-turut.',
    },
    {
        icon: Copy,
        title: 'Gambar berulang',
        text: 'Salinan screenshot, termasuk yang disimpan ulang atau disunting.',
    },
    {
        icon: Recycle,
        title: 'Daur ulang',
        text: 'Screenshot hari lain atau milik rekan kerja dipakai lagi.',
    },
    {
        icon: Clock,
        title: 'Jam tidak cocok',
        text: 'Jam di taskbar berbeda dengan waktu pada nama file.',
    },
    {
        icon: Hourglass,
        title: 'Celah waktu',
        text: 'Ada jeda tanpa screenshot yang lebih lama dari biasanya.',
    },
    {
        icon: Gamepad2,
        title: 'Aplikasi non-kerja',
        text: 'YouTube, game, media sosial, atau belanja online, dikenali AI.',
    },
    {
        icon: FileSpreadsheet,
        title: 'Laporan siap pakai',
        text: 'Ringkasan dan bukti visual dalam Excel maupun PDF.',
    },
];

const promises = [
    {
        icon: BadgeCheck,
        title: 'Indikasi, bukan vonis',
        text: 'Setiap temuan disertai bukti gambar dan penjelasan, sehingga keputusan tetap di tangan Anda.',
    },
    {
        icon: ServerCog,
        title: 'Data tetap di server Anda',
        text: 'Screenshot dianalisis di server sendiri, termasuk AI-nya. Tidak dikirim ke layanan luar.',
    },
    {
        icon: LockKeyhole,
        title: 'Akses terbatas',
        text: 'Akun hanya lewat undangan team, dengan pengaturan khusus pemilik dan admin.',
    },
];

const timeline = [
    'bg-emerald-500',
    'bg-emerald-500',
    'bg-emerald-500',
    'bg-red-500',
    'bg-red-500',
    'bg-emerald-500',
    'bg-fuchsia-600',
    'bg-emerald-500',
    'bg-amber-400',
    'bg-emerald-500',
    'bg-emerald-500',
    'bg-emerald-500',
];
</script>

<template>
    <Head title="Cek jejak kerja dari screenshot" />

    <div class="min-h-screen bg-background text-foreground">
        <header
            class="sticky top-0 z-10 border-b border-border/60 bg-background/85 backdrop-blur"
        >
            <nav
                class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 md:px-6"
            >
                <Link href="/" class="flex items-center gap-2.5">
                    <span
                        class="flex size-9 items-center justify-center rounded-xl bg-primary text-primary-foreground"
                    >
                        <AppLogoIcon class="size-5" />
                    </span>
                    <span class="text-lg font-bold tracking-tight">
                        {{ appName }}
                    </span>
                </Link>

                <div class="flex items-center gap-2">
                    <Button
                        v-if="$page.props.auth.user"
                        class="rounded-xl"
                        as-child
                    >
                        <Link :href="dashboardUrl">
                            Buka beranda <ArrowRight />
                        </Link>
                    </Button>
                    <template v-else>
                        <Button
                            v-if="canRegister"
                            variant="ghost"
                            class="rounded-xl"
                            as-child
                        >
                            <Link :href="register()">Daftar</Link>
                        </Button>
                        <Button class="rounded-xl" as-child>
                            <Link :href="login()">Masuk</Link>
                        </Button>
                    </template>
                </div>
            </nav>
        </header>

        <main>
            <section
                class="mx-auto grid max-w-6xl items-center gap-12 px-4 py-14 md:px-6 md:py-20 lg:grid-cols-[1.1fr_1fr]"
            >
                <div class="space-y-6">
                    <span
                        class="inline-flex items-center gap-2 rounded-full bg-accent px-3 py-1 text-xs font-semibold text-accent-foreground"
                    >
                        Untuk tim HR & atasan
                    </span>
                    <h1
                        class="text-4xl leading-tight font-bold tracking-tight md:text-5xl"
                    >
                        Cek jejak kerja dari screenshot,
                        <span class="text-primary">lebih cepat dan adil.</span>
                    </h1>
                    <p class="max-w-xl text-lg text-muted-foreground">
                        Unggah ratusan screenshot dari aplikasi pemantau kerja.
                        {{ appName }} menandai layar diam, gambar salinan,
                        aplikasi non-kerja, dan jam yang janggal, lengkap dengan
                        buktinya.
                    </p>
                    <div class="flex flex-wrap gap-3">
                        <Button size="lg" class="rounded-xl" as-child>
                            <Link
                                :href="
                                    $page.props.auth.user
                                        ? dashboardUrl
                                        : login()
                                "
                            >
                                {{
                                    $page.props.auth.user
                                        ? 'Buka beranda'
                                        : 'Masuk untuk mulai'
                                }}
                                <ArrowRight />
                            </Link>
                        </Button>
                        <Button
                            size="lg"
                            variant="outline"
                            class="rounded-xl"
                            as-child
                        >
                            <a href="#cara-kerja">Lihat cara kerja</a>
                        </Button>
                    </div>
                </div>

                <div
                    aria-hidden="true"
                    class="relative rounded-3xl border bg-card p-5 shadow-xl shadow-primary/10"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="flex size-10 items-center justify-center rounded-xl bg-primary font-bold text-primary-foreground"
                            >B</span
                        >
                        <div>
                            <p class="font-semibold">Budi Santoso</p>
                            <p class="text-xs text-muted-foreground">
                                Sen, 6 Okt 2026 · 96 screenshot
                            </p>
                        </div>
                        <span
                            class="ml-auto rounded-full bg-primary px-2.5 py-0.5 text-xs font-semibold text-primary-foreground"
                            >Selesai</span
                        >
                    </div>

                    <div class="mt-5 grid grid-cols-3 gap-2">
                        <div
                            class="rounded-xl bg-emerald-50 p-3 dark:bg-emerald-950"
                        >
                            <p
                                class="text-xl font-bold text-emerald-700 dark:text-emerald-300"
                            >
                                88%
                            </p>
                            <p
                                class="text-xs text-emerald-800 dark:text-emerald-300"
                            >
                                Layar aktif
                            </p>
                        </div>
                        <div class="rounded-xl bg-red-50 p-3 dark:bg-red-950">
                            <p
                                class="text-xl font-bold text-red-700 dark:text-red-300"
                            >
                                2
                            </p>
                            <p class="text-xs text-red-800 dark:text-red-300">
                                Layar diam
                            </p>
                        </div>
                        <div
                            class="rounded-xl bg-fuchsia-50 p-3 dark:bg-fuchsia-950"
                        >
                            <p
                                class="text-xl font-bold text-fuchsia-700 dark:text-fuchsia-300"
                            >
                                1
                            </p>
                            <p
                                class="text-xs text-fuchsia-800 dark:text-fuchsia-300"
                            >
                                Gambar berulang
                            </p>
                        </div>
                    </div>

                    <div class="mt-5">
                        <p
                            class="mb-2 text-xs font-medium text-muted-foreground"
                        >
                            Timeline 08:00 – 09:00
                        </p>
                        <div
                            class="flex h-9 items-center gap-1.5 rounded-lg bg-muted/70 px-2"
                        >
                            <span
                                v-for="(color, index) in timeline"
                                :key="index"
                                class="h-6 flex-1 rounded-sm"
                                :class="color"
                            />
                        </div>
                    </div>

                    <div class="mt-5 space-y-2">
                        <div
                            class="flex items-center gap-3 rounded-xl border p-2.5"
                        >
                            <span
                                class="flex size-8 items-center justify-center rounded-lg bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300"
                            >
                                <MonitorPause class="size-4" />
                            </span>
                            <p class="text-xs">
                                <span class="font-semibold">08:20</span> · Layar
                                hanya berubah 0,08% dibanding 08:15
                            </p>
                        </div>
                        <div
                            class="flex items-center gap-3 rounded-xl border p-2.5"
                        >
                            <span
                                class="flex size-8 items-center justify-center rounded-lg bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-950 dark:text-fuchsia-300"
                            >
                                <Copy class="size-4" />
                            </span>
                            <p class="text-xs">
                                <span class="font-semibold">08:30</span> ·
                                Hampir sama persis dengan screenshot 08:05
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section id="cara-kerja" class="border-y bg-card/60 py-16">
                <div class="mx-auto max-w-6xl px-4 md:px-6">
                    <h2 class="text-center text-3xl font-bold tracking-tight">
                        Cara kerjanya
                    </h2>
                    <p
                        class="mx-auto mt-2 max-w-xl text-center text-muted-foreground"
                    >
                        Tiga langkah, tanpa perlu membuka screenshot satu per
                        satu.
                    </p>
                    <ol class="mt-10 grid gap-4 md:grid-cols-3">
                        <li
                            v-for="(step, index) in steps"
                            :key="step.title"
                            class="rounded-2xl border bg-card p-6 shadow-sm"
                        >
                            <div class="flex items-center gap-3">
                                <span
                                    class="flex size-10 items-center justify-center rounded-xl bg-primary text-primary-foreground"
                                >
                                    <component :is="step.icon" class="size-5" />
                                </span>
                                <span
                                    class="text-sm font-semibold text-muted-foreground"
                                >
                                    Langkah {{ index + 1 }}
                                </span>
                            </div>
                            <h3 class="mt-4 font-semibold">{{ step.title }}</h3>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ step.text }}
                            </p>
                        </li>
                    </ol>
                </div>
            </section>

            <section class="mx-auto max-w-6xl px-4 py-16 md:px-6">
                <h2 class="text-center text-3xl font-bold tracking-tight">
                    Apa yang diperiksa
                </h2>
                <p
                    class="mx-auto mt-2 max-w-xl text-center text-muted-foreground"
                >
                    Setiap screenshot dibandingkan dengan screenshot lain di
                    hari yang sama, hari sebelumnya, dan milik rekan satu team.
                </p>
                <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div
                        v-for="item in detections"
                        :key="item.title"
                        class="rounded-2xl border bg-card p-5 shadow-sm"
                    >
                        <span
                            class="flex size-10 items-center justify-center rounded-xl bg-accent text-accent-foreground"
                        >
                            <component :is="item.icon" class="size-5" />
                        </span>
                        <h3 class="mt-4 font-semibold">{{ item.title }}</h3>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ item.text }}
                        </p>
                    </div>
                </div>
            </section>

            <section class="border-t bg-card/60 py-16">
                <div
                    class="mx-auto grid max-w-6xl gap-4 px-4 md:grid-cols-3 md:px-6"
                >
                    <div
                        v-for="item in promises"
                        :key="item.title"
                        class="flex gap-4 rounded-2xl p-2"
                    >
                        <span
                            class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
                        >
                            <component :is="item.icon" class="size-5" />
                        </span>
                        <div>
                            <h3 class="font-semibold">{{ item.title }}</h3>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ item.text }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-6xl px-4 py-16 md:px-6">
                <div
                    class="flex flex-col items-start justify-between gap-6 rounded-3xl bg-primary p-8 text-primary-foreground md:flex-row md:items-center md:p-10"
                >
                    <div>
                        <h2
                            class="text-2xl font-bold tracking-tight md:text-3xl"
                        >
                            Siap mengecek screenshot pertama?
                        </h2>
                        <p class="mt-2 text-primary-foreground/85">
                            Masuk dengan akun dari undangan team Anda.
                        </p>
                    </div>
                    <Button
                        size="lg"
                        variant="secondary"
                        class="rounded-xl bg-white text-primary hover:bg-white/90"
                        as-child
                    >
                        <Link
                            :href="
                                $page.props.auth.user ? dashboardUrl : login()
                            "
                        >
                            {{
                                $page.props.auth.user ? 'Buka beranda' : 'Masuk'
                            }}
                            <ArrowRight />
                        </Link>
                    </Button>
                </div>
            </section>
        </main>

        <footer class="border-t">
            <div
                class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-6 text-sm text-muted-foreground md:px-6"
            >
                <span class="flex items-center gap-2">
                    <span
                        class="flex size-6 items-center justify-center rounded-md bg-primary text-primary-foreground"
                    >
                        <AppLogoIcon class="size-3.5" />
                    </span>
                    {{ appName }}
                </span>
                <span
                    >Cek jejak kerja dari screenshot, lebih cepat dan
                    adil.</span
                >
            </div>
        </footer>
    </div>
</template>
