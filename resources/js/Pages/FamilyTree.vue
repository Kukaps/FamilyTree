<template>
    <div class="min-h-screen bg-gray-100">
        <!-- Header -->
        <header class="bg-white shadow">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                <h1 class="text-3xl font-bold text-gray-900">Family Tree Visualizer</h1>
            </div>
        </header>

        <main class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Instructions Card -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <h2 class="text-xl font-semibold mb-4">How to Use</h2>
                        <ol class="list-decimal list-inside space-y-2 text-gray-600">
                            <li>Go to <a href="https://www.familyecho.com" target="_blank" class="text-blue-600 hover:text-blue-800">Family Echo</a></li>
                            <li>Create your family tree</li>
                            <li>Export as GEDCOM</li>
                            <li>Paste the GEDCOM data below</li>
                        </ol>
                    </div>
                </div>

                <!-- Main Content Card -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- Input Section -->
                            <div class="space-y-4">
                                <h2 class="text-xl font-semibold mb-4">Input GEDCOM Data</h2>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <family-tree></family-tree>
                                </div>
                            </div>

                            <!-- Information Section -->
                            <div class="space-y-4">
                                <h2 class="text-xl font-semibold mb-4">About Family Trees</h2>
                                <div class="prose max-w-none">
                                    <p class="text-gray-600">
                                        A family tree, also called a genealogy or a pedigree chart, is a chart representing family relationships in a conventional tree structure.
                                    </p>
                                    <div class="mt-4">
                                        <h3 class="text-lg font-medium text-gray-900">Features:</h3>
                                        <ul class="list-disc list-inside text-gray-600 mt-2">
                                            <li>Interactive visualization</li>
                                            <li>Zoom and pan capabilities</li>
                                            <li>Multiple generations support</li>
                                            <li>GEDCOM format compatible</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="bg-white border-t border-gray-200 mt-12">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                <p class="text-center text-gray-500 text-sm">
                    Family Tree Visualizer © {{ new Date().getFullYear() }}
                </p>
            </div>
        </footer>
    </div>
</template>

<script>
import FamilyTree from '@/Components/FamilyTree.vue'

export default {
    components: {
        FamilyTree
    }
}
</script> 