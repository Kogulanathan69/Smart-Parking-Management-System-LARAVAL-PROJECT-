<template x-if="token">
    <div class="alert-warning mb-4" role="alert">
        <p class="font-semibold">Device token for <span x-text="token.code"></span> — shown only once</p>
        <p class="mt-1 text-sm">Copy it into this camera's agent configuration now (ANPR_CAMERA_TOKEN). It cannot be shown again; if lost, rotate it.</p>
        <div class="mt-2 flex flex-col gap-2 sm:flex-row"><input class="input font-mono text-xs" :value="token.value" readonly @focus="$event.target.select()" aria-label="Device token">
            <button type="button" class="btn-secondary" @click="navigator.clipboard?.writeText(token.value).then(() => ParkFlow.toast('Copied.'))">Copy</button>
            <button type="button" class="btn-primary" @click="token = null">I have stored it</button></div>
    </div>
</template>
