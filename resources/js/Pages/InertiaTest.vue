<script setup>
// import Layout from './Layout'
import { Head } from '@inertiajs/vue3'

defineProps({ username: String })
</script>

<template>
    <div>
        <Head title="Welcome" />
        <h1>Welcome</h1>
        <p>Hello {{ username ?? 'Guest' }}, welcome to Inertia Test!</p>
    </div>
</template>