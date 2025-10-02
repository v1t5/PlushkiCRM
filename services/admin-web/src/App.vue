<script setup lang="ts">
// Top-level shell is just the router outlet. The main layout (sidebar +
// content area) is in views/Shell.vue, mounted as the parent of the
// authenticated routes; views/Login.vue mounts as a sibling at /login
// so it doesn't inherit the chrome.
</script>

<template>
  <RouterView />
</template>
