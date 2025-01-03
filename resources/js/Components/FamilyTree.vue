<template>
    <div class="family-tree-container">
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
    </div>
</template>

<script>
import * as d3 from 'd3';

export default {
    data() {
        return {
            gedcomData: '',
            treeData: null,
            svg: null,
            width: 800,
            height: 600,
            isLoading: false,
            errorMessage: null
        };
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
            if (!this.svg || !this.treeData) return;

            console.log('Rendering tree with data:', this.treeData);

            // Clear existing tree
            this.svg.selectAll('*').remove();

            // Creates a tree layout with more space
            const treeLayout = d3.tree()
                .size([this.height - 80, this.width - 200]);

            // Creates a hierarchy from the data
            const root = d3.hierarchy(this.treeData);
            
            // Assigns the data to the tree layout
            const treeData = treeLayout(root);

            // Add links between nodes
            this.svg.selectAll('.link')
                .data(treeData.links())
                .enter()
                .append('path')
                .attr('class', 'link')
                .attr('d', d3.linkHorizontal()
                    .x(d => d.y)
                    .y(d => d.x));

            // Add nodes with more detailed information
            const nodes = this.svg.selectAll('.node')
                .data(treeData.descendants())
                .enter()
                .append('g')
                .attr('class', 'node')
                .attr('transform', d => `translate(${d.y},${d.x})`);

            // Add circles for nodes
            nodes.append('circle')
                .attr('r', 8)
                .style('fill', '#fff')
                .style('stroke', '#3b82f6')
                .style('stroke-width', '2px');

            // Add labels with more spacing
            nodes.append('text')
                .attr('dy', '0.31em')
                .attr('x', d => d.children ? -12 : 12)
                .attr('text-anchor', d => d.children ? 'end' : 'start')
                .text(d => d.data.name)
                .style('font-size', '12px')
                .style('font-family', 'sans-serif');
        },

        parseGedcom(gedcomData) {
            console.log('Starting GEDCOM parsing');
            const lines = gedcomData.split('\n');
            const individuals = new Map();
            const families = new Map();
            let currentRecord = null;
            let currentType = null;

            // First pass: collect individuals and families
            lines.forEach(line => {
                const parts = line.trim().split(' ');
                const level = parts[0];
                
                if (level === '0') {
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
                        console.log('Found individual:', currentRecord);
                    } else if (parts[1].startsWith('@F')) {
                        currentRecord = parts[1];
                        currentType = 'FAM';
                        families.set(currentRecord, {
                            id: currentRecord,
                            husband: null,
                            wife: null,
                            children: []
                        });
                        console.log('Found family:', currentRecord);
                    }
                } else if (currentRecord) {
                    if (currentType === 'INDI') {
                        const tag = parts[1];
                        const value = parts.slice(2).join(' ');
                        const individual = individuals.get(currentRecord);

                        switch(tag) {
                            case 'NAME':
                                individual.name = value.replace(/\//g, '');
                                console.log('Set name:', individual.name);
                                break;
                            case 'SEX':
                                individual.sex = value;
                                break;
                            case 'FAMC':
                                individual.parents.push(value);
                                console.log(`Added parent family ${value} to ${individual.name}`);
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
                                console.log(`Added husband ${value} to family ${currentRecord}`);
                                break;
                            case 'WIFE':
                                family.wife = value;
                                console.log(`Added wife ${value} to family ${currentRecord}`);
                                break;
                            case 'CHIL':
                                family.children.push(value);
                                console.log(`Added child ${value} to family ${currentRecord}`);
                                break;
                        }
                    }
                }
            });

            console.log('Parsed Individuals:', [...individuals.entries()]);
            console.log('Parsed Families:', [...families.entries()]);

            // Find root person (Kristaps)
            const rootPerson = individuals.get('@I1@');
            return this.buildTreeStructure(rootPerson.id, individuals, families);
        },

        buildTreeStructure(personId, individuals, families) {
            const person = individuals.get(personId);
            if (!person) {
                console.log('Person not found:', personId);
                return null;
            }

            console.log('Building tree for:', person.name, 'ID:', personId);

            const node = {
                name: person.name,
                id: personId,
                children: []
            };

            // Process parent families
            for (const familyId of person.parents) {
                console.log(`Processing family ${familyId} for person ${person.name}`);
                const family = families.get(familyId);
                
                if (family) {
                    // Process father
                    if (family.husband) {
                        const father = individuals.get(family.husband);
                        console.log(`Found father: ${father?.name} (${family.husband})`);
                        const fatherNode = this.buildTreeStructure(family.husband, individuals, families);
                        if (fatherNode) {
                            node.children.push(fatherNode);
                        }
                    }

                    // Process mother
                    if (family.wife) {
                        const mother = individuals.get(family.wife);
                        console.log(`Found mother: ${mother?.name} (${family.wife})`);
                        const motherNode = this.buildTreeStructure(family.wife, individuals, families);
                        if (motherNode) {
                            node.children.push(motherNode);
                        }
                    }
                }
            }

            return node;
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
