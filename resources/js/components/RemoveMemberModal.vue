<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { destroy as destroyMember } from '@/routes/teams/members';
import type { Team, TeamMember } from '@/types';

type Props = {
    team: Team;
    member: TeamMember | null;
    open: boolean;
};

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const processing = ref(false);

const removeMember = () => {
    if (!props.member) {
        return;
    }

    router.visit(destroyMember([props.team.slug, props.member.id]), {
        onStart: () => (processing.value = true),
        onFinish: () => (processing.value = false),
        onSuccess: () => emit('update:open', false),
    });
};
</script>

<template>
    <Dialog :open="props.open" @update:open="emit('update:open', $event)">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Keluarkan anggota team</DialogTitle>
                <DialogDescription>
                    Yakin ingin mengeluarkan
                    <strong>{{ props.member?.name }}</strong> dari team ini?
                </DialogDescription>
            </DialogHeader>

            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary"> Batal </Button>
                </DialogClose>

                <Button
                    data-test="remove-member-confirm"
                    variant="destructive"
                    :disabled="processing"
                    @click="removeMember"
                >
                    Keluarkan anggota
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
