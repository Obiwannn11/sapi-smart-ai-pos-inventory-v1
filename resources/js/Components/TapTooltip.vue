<script setup>
/**
 * TapTooltip — label for an icon-only button that a touch user can actually read.
 *
 * The `title` attribute only ever appears on hover, so on the tablets and phones
 * the till runs on it is dead markup: the kasir sees a bare icon and no way to
 * learn what it does. This wraps the button instead and reveals the label on
 * hover (mouse), on focus (keyboard), and on press-and-hold (touch).
 *
 * Press-and-hold rather than a plain tap, because the trigger is a button that
 * already does something. A plain tap would have to either fire the action —
 * leaving the label to flash after the fact, too late to be a hint — or swallow
 * the first tap of every button in the topbar, which on a till is worse than no
 * tooltip at all. Holding is what Android's own toolbars do, and it keeps a
 * quick tap exactly as fast as it is today: the hold is only recognised at
 * `HOLD_MS`, and only then is the click that follows suppressed.
 *
 * The label is `aria-hidden`: the button it wraps carries its own `aria-label`,
 * so exposing this bubble too would just make a screen reader say it twice.
 */

import { onBeforeUnmount, ref } from 'vue';

defineProps({
    label: { type: String, required: true },
    /**
     * Which edge of the trigger the bubble lines up with. Centred by default;
     * pass `right` for a trigger near the right edge of the screen, where a
     * centred bubble would spill off it.
     */
    align: {
        type: String,
        default: 'center',
        validator: (value) => ['center', 'right'].includes(value),
    },
});

/** How long a touch must be held before it counts as "show me the label". */
const HOLD_MS = 450;

/** How long the label lingers after a hold, since there is no pointer to leave. */
const LINGER_MS = 2000;

const visible = ref(false);

let holdTimer = null;
let lingerTimer = null;
let suppressClick = false;

const clearTimers = () => {
    clearTimeout(holdTimer);
    clearTimeout(lingerTimer);
    holdTimer = null;
    lingerTimer = null;
};

const hide = () => {
    clearTimers();
    visible.value = false;
};

const onPointerEnter = (event) => {
    if (event.pointerType === 'mouse') {
        visible.value = true;
    }
};

const onPointerDown = (event) => {
    // Always start from a clean slate: a hold that never produced a click (some
    // browsers skip it) must not leave the suppression armed for the next tap.
    suppressClick = false;
    clearTimers();

    if (event.pointerType === 'mouse') {
        return;
    }

    holdTimer = setTimeout(() => {
        visible.value = true;
        suppressClick = true;
    }, HOLD_MS);
};

const onPointerUp = () => {
    clearTimeout(holdTimer);
    holdTimer = null;

    if (visible.value && suppressClick) {
        lingerTimer = setTimeout(hide, LINGER_MS);
    }
};

/**
 * Runs in the capture phase, so it lands before the wrapped button's own
 * handler and can stop the click from ever reaching it.
 */
const onClickCapture = (event) => {
    if (!suppressClick) {
        return;
    }

    suppressClick = false;
    event.preventDefault();
    event.stopPropagation();
};

/** Long-press pops the OS callout on touch; suppress it only while holding. */
const onContextMenu = (event) => {
    if (holdTimer !== null || visible.value) {
        event.preventDefault();
    }
};

onBeforeUnmount(clearTimers);
</script>

<template>
    <span
        class="relative inline-flex select-none [-webkit-touch-callout:none]"
        @pointerenter="onPointerEnter"
        @pointerleave="hide"
        @pointerdown="onPointerDown"
        @pointerup="onPointerUp"
        @pointercancel="hide"
        @focusin="visible = true"
        @focusout="hide"
        @click.capture="onClickCapture"
        @contextmenu="onContextMenu"
    >
        <slot />

        <Transition
            enter-active-class="transition duration-100 ease-out"
            enter-from-class="opacity-0 translate-y-0.5"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-75 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <span
                v-if="visible"
                class="pointer-events-none absolute top-full z-50 mt-1.5 whitespace-nowrap rounded-md bg-foreground px-2 py-1 text-xs font-medium text-background shadow-lg"
                :class="align === 'right' ? 'right-0' : 'left-1/2 -translate-x-1/2'"
                role="tooltip"
                aria-hidden="true"
            >
                {{ label }}
            </span>
        </Transition>
    </span>
</template>
