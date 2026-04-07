<template>
    <div>
        <!-- Loop to create skeleton cards based on 'page' value -->
        <div v-for="n in page" :key="n" class="skeleton-card">
            <div class="skeleton-card-left">
                <div class="skeleton-text-group">
                    <div class="skeleton-title skeleton"></div>
                    <div class="skeleton-subtitle skeleton"></div>
                </div>
            </div>
            <div class="skeleton-badge skeleton"></div>
        </div>
    </div>
</template>

<script>
export default {
    props: {
        // Define 'page' as a prop with a default value of 1
        page: {
            type: Number,
            default: 1,
        },
    },
};
</script>

<style scoped>
/* Animasi shimmer */
@keyframes shimmer {
    100% {
        transform: translateX(100%);
    }
}

/* Base untuk shimmer efek */
.skeleton {
    position: relative;
    overflow: hidden;
    background-color: #e2e5e7;
    border-radius: 0.375rem;
}

.skeleton::after {
    content: "";
    position: absolute;
    top: 0;
    left: -100%;
    height: 100%;
    width: 100%;
    background: linear-gradient(
        to right,
        transparent,
        rgba(255, 255, 255, 0.6),
        transparent
    );
    animation: shimmer 1.5s infinite;
}

/* Container Card */
.skeleton-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.25rem;
    border-radius: 0.75rem;
    background-color: #ffffff;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
    margin-bottom: 1rem;
    flex-wrap: wrap;
}

/* Left Side (Icon + Text) */
.skeleton-card-left {
    display: flex;
    align-items: center;
    gap: 1rem;
    min-width: 0;
    flex: 1;
}

/* Icon Placeholder */
.skeleton-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
}

/* Text Placeholders */
.skeleton-text-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.skeleton-title {
    width: 140px;
    height: 14px;
    border-radius: 6px;
}

.skeleton-subtitle {
    width: 100px;
    height: 10px;
    border-radius: 6px;
}

/* Right Side Badge */
.skeleton-badge {
    width: 70px;
    height: 24px;
    border-radius: 9999px;
    flex-shrink: 0;
}

/* Responsive */
@media (max-width: 576px) {
    .skeleton-card {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }

    .skeleton-badge {
        align-self: flex-end;
    }
}
</style>
