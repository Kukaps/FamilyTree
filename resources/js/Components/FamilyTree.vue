<template>
    <div class="family-tree-container">
        <!-- Add this before your existing form -->
        <div v-if="savedTrees.length > 0" class="mb-6">
            <h3 class="text-lg font-semibold mb-2">Saved Family Trees</h3>
            <div class="grid gap-4">
                <div v-for="tree in savedTrees" 
                     :key="tree.id" 
                     class="bg-white p-4 rounded-lg shadow flex justify-between items-center">
                    <div>
                        <h4 class="font-medium">{{ tree.name }}</h4>
                        <p class="text-sm text-gray-500">Created: {{ tree.created_at }}</p>
                        <p class="text-sm text-gray-500">Members: {{ tree.total_individuals }}</p>
                    </div>
                    <button @click="loadTree(tree.id)"
                            class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                        Load
                    </button>
                </div>
            </div>
        </div>

        <!-- Input Form -->
        <form @submit.prevent="parseAndRender" class="space-y-4">
            <div>
                <label for="gedcom-data" class="block text-sm font-medium text-gray-700 mb-2">
                    GEDCOM Data
                </label>
                <textarea 
                    id="gedcom-data"
                    v-model="gedcomData" 
                    placeholder="Paste your GEDCOM data here..." 
                    class="w-full p-3 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                    rows="10"
                ></textarea>
            </div>

            <button 
                type="submit"
                class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                :disabled="isLoading"
            >
                <span v-if="isLoading" class="flex items-center justify-center">
                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Processing...
                </span>
                <span v-else>
                    Visualize Tree
                </span>
            </button>
        </form>

        <!-- Error Message -->
        <div v-if="errorMessage" class="mt-4 p-4 bg-red-50 border border-red-200 rounded-md">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800">Error</h3>
                    <p class="mt-1 text-sm text-red-600">{{ errorMessage }}</p>
                </div>
            </div>
        </div>

        <!-- Tree Viewer -->
        <div 
            v-show="treeData"
            ref="treeContainer"
            class="mt-6 border border-gray-200 rounded-lg bg-white shadow-inner"
            style="height: 600px; overflow: auto;"
        >
            <!-- D3 will render here -->
        </div>

        <!-- Add this button after the existing form -->
        <button 
            v-if="gedcomData"
            @click="saveToDatabase" 
            class="mt-4 w-full bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700"
            :disabled="isSaving"
        >
            {{ isSaving ? 'Saving...' : 'Save to Database' }}
        </button>
    </div>
</template>

<script>
import * as d3 from 'd3';
import { router } from '@inertiajs/vue3'

export default {
    data() {
        return {
            gedcomData: '',
            treeData: null,
            svg: null,
            width: 800,
            height: 600,
            isLoading: false,
            errorMessage: null,
            isSaving: false,
            savedTrees: []
        };
    },
    mounted() {
        window.addEventListener('resize', this.handleResize);
    },
    beforeDestroy() {
        window.removeEventListener('resize', this.handleResize);
    },
    async mounted() {
        // Add this to check auth status
        try {
            const response = await fetch('/api/user', {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                credentials: 'include'
            });
            console.log('Authenticated user:', response.data);
        } catch (error) {
            console.error('Auth check failed:', error);
        }
        
        await this.loadSavedTrees();
    },
    methods: {
        async parseAndRender() {
            this.isLoading = true;
            this.errorMessage = null;
            
            try {
                this.treeData = this.parseGedcom(this.gedcomData);
                await this.$nextTick();
                this.initializeSVG();
                this.renderTree();
            } catch (error) {
                console.error('Error parsing GEDCOM:', error);
                this.errorMessage = 'Error parsing GEDCOM data. Please check the format.';
            } finally {
                this.isLoading = false;
            }
        },

        initializeSVG() {
            if (!this.$refs.treeContainer) return;

            // Clear any existing SVG
            d3.select(this.$refs.treeContainer).selectAll('svg').remove();

            // Create new SVG
            this.svg = d3.select(this.$refs.treeContainer)
                .append('svg')
                .attr('width', this.width)
                .attr('height', this.height)
                .append('g')
                .attr('transform', 'translate(40,0)');

            // Add zoom behavior
            const zoom = d3.zoom()
                .scaleExtent([0.1, 3])
                .on('zoom', (event) => {
                    this.svg.attr('transform', event.transform);
                });

            d3.select(this.$refs.treeContainer).select('svg').call(zoom);
        },

        renderTree() {
            if (!this.svg || !this.treeData) {
                console.log('No SVG or tree data');
                return;
            }

            console.log('Rendering tree with data:', this.treeData);

            // Clear existing tree
            this.svg.selectAll('*').remove();

            // Creates a tree layout
            const treeLayout = d3.tree()
                .size([this.height - 80, this.width - 200]);

            // Creates a hierarchy from the data
            const root = d3.hierarchy(this.treeData);
            
            // Assigns the data to the tree layout
            const treeData = treeLayout(root);

            console.log('Processed tree data:', treeData);

            // Add links between nodes
            const links = this.svg.selectAll('.link')
                .data(treeData.links())
                .enter()
                .append('path')
                .attr('class', 'link')
                .attr('d', d3.linkHorizontal()
                    .x(d => d.y)
                    .y(d => d.x));

            // Add nodes
            const nodes = this.svg.selectAll('.node')
                .data(treeData.descendants())
                .enter()
                .append('g')
                .attr('class', 'node')
                .attr('transform', d => `translate(${d.y},${d.x})`);

            // Add circles for nodes
            nodes.append('circle')
                .attr('r', 8);

            // Add labels
            nodes.append('text')
                .attr('dy', '.31em')
                .attr('x', d => d.children ? -12 : 12)
                .attr('text-anchor', d => d.children ? 'end' : 'start')
                .text(d => d.data.name);
        },

        parseGedcom(gedcomData) {
            if (!gedcomData.trim()) {
                throw new Error('GEDCOM data is empty');
            }

            console.log('Starting GEDCOM parsing');
            const lines = gedcomData.split('\n');
            const individuals = new Map();
            const families = new Map();
            let currentRecord = null;
            let currentType = null;

            try {
                // First pass: collect individuals and families
                lines.forEach((line, index) => {
                    const parts = line.trim().split(/\s+/);
                    if (parts.length < 2) return;

                    const level = parseInt(parts[0]);
                    if (level === 0) {
                        if (parts[1].startsWith('@I')) {
                            currentRecord = parts[1];
                            currentType = 'INDI';
                            individuals.set(currentRecord, {
                                id: currentRecord,
                                name: '',
                                sex: '',
                                birth: '',
                                parents: [],
                                families: []
                            });
                        } else if (parts[1].startsWith('@F')) {
                            currentRecord = parts[1];
                            currentType = 'FAM';
                            families.set(currentRecord, {
                                id: currentRecord,
                                husband: null,
                                wife: null,
                                children: []
                            });
                        } else {
                            currentRecord = null;
                            currentType = null;
                        }
                    } else if (currentRecord) {
                        if (currentType === 'INDI') {
                            const tag = parts[1];
                            const value = parts.slice(2).join(' ');
                            const individual = individuals.get(currentRecord);

                            switch(tag) {
                                case 'NAME':
                                    individual.name = value.replace(/\//g, '').trim();
                                    break;
                                case 'SEX':
                                    individual.sex = value;
                                    break;
                                case 'FAMC':
                                    individual.parents.push(value);
                                    break;
                                case 'FAMS':
                                    individual.families.push(value);
                                    break;
                            }
                        } else if (currentType === 'FAM') {
                            const tag = parts[1];
                            const value = parts[2];
                            const family = families.get(currentRecord);

                            switch(tag) {
                                case 'HUSB':
                                    family.husband = value;
                                    break;
                                case 'WIFE':
                                    family.wife = value;
                                    break;
                                case 'CHIL':
                                    family.children.push(value);
                                    break;
                            }
                        }
                    }
                });

                console.log('Parsed Individuals:', [...individuals.entries()]);
                console.log('Parsed Families:', [...families.entries()]);

                // Start with @I1@ as the root
                const rootPerson = individuals.get('@I1@');
                if (!rootPerson) {
                    throw new Error('Root person (@I1@) not found');
                }

                return this.buildTreeStructure('@I1@', individuals, families);

            } catch (error) {
                console.error('GEDCOM parsing error:', error);
                throw new Error(`GEDCOM parsing failed: ${error.message}`);
            }
        },

        buildTreeStructure(personId, individuals, families, processed = new Set()) {
            console.log('Processing person:', personId);
            
            // Prevent infinite recursion
            if (processed.has(personId)) {
                console.log('Already processed:', personId);
                return null;
            }
            processed.add(personId);

            const person = individuals.get(personId);
            if (!person) {
                console.log('Person not found:', personId);
                return null;
            }

            console.log('Building node for:', person.name);

            const node = {
                name: person.name || 'Unknown',
                id: personId,
                children: []
            };

            // Find all families where this person is a child
            const parentFamilies = [...families.values()].filter(f => 
                f.children && f.children.includes(personId)
            );

            // Add parents
            for (const family of parentFamilies) {
                console.log('Processing parent family:', family.id);
                
                if (family.husband) {
                    console.log('Adding father:', family.husband);
                    const fatherNode = this.buildTreeStructure(family.husband, individuals, families, new Set(processed));
                    if (fatherNode) node.children.push(fatherNode);
                }
                
                if (family.wife) {
                    console.log('Adding mother:', family.wife);
                    const motherNode = this.buildTreeStructure(family.wife, individuals, families, new Set(processed));
                    if (motherNode) node.children.push(motherNode);
                }
            }

            // Find all families where this person is a parent
            const ownFamilies = [...families.values()].filter(f => 
                f.husband === personId || f.wife === personId
            );

            // Add children
            for (const family of ownFamilies) {
                console.log('Processing own family:', family.id);
                
                if (family.children) {
                    for (const childId of family.children) {
                        console.log('Adding child:', childId);
                        const childNode = this.buildTreeStructure(childId, individuals, families, new Set(processed));
                        if (childNode) node.children.push(childNode);
                    }
                }
            }

            console.log(`Finished node ${person.name} with ${node.children.length} children`);
            return node;
        },

        async saveToDatabase() {
            if (!this.gedcomData) return;
            
            this.isSaving = true;
            try {
                const response = await fetch('/api/store-family-tree', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        gedcomData: this.gedcomData
                    }),
                    credentials: 'include'
                });

                if (!response.ok) {
                    throw new Error('Failed to save');
                }

                const data = await response.json();
                alert('Family tree saved successfully!');
                await this.loadSavedTrees();
            } catch (error) {
                console.error('Failed to save family tree:', error);
                alert('Failed to save family tree: ' + error.message);
            } finally {
                this.isSaving = false;
            }
        },

        async loadSavedTrees() {
            try {
                const response = await fetch('/api/family-trees', {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    credentials: 'include'
                });

                if (!response.ok) {
                    throw new Error('Failed to load trees');
                }

                const data = await response.json();
                this.savedTrees = data;
            } catch (error) {
                console.error('Failed to load saved trees:', error);
            }
        },

        async loadTree(id) {
            this.isLoading = true;
            try {
                const response = await fetch(`/api/family-trees/${id}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    credentials: 'include'
                });

                if (!response.ok) {
                    throw new Error('Failed to load tree');
                }

                const data = await response.json();
                this.treeData = data;
                this.renderTree();
            } catch (error) {
                console.error('Failed to load tree:', error);
                alert('Failed to load family tree');
            } finally {
                this.isLoading = false;
            }
        }
    }
};
</script>

<style scoped>
.family-tree-container {
    @apply p-4;
}

#tree-container {
    background: white;
}

:deep(.node circle) {
    @apply transition-all duration-300 ease-in-out;
    fill: white;
    stroke: #3b82f6;
    stroke-width: 2px;
}

:deep(.node text) {
    @apply text-sm font-sans;
}

:deep(.node:hover circle) {
    @apply fill-gray-100;
    stroke: #2563eb;
    stroke-width: 3px;
}

:deep(.link) {
    @apply stroke-gray-300;
    fill: none;
    stroke-width: 2px;
}
</style>
