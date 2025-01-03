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

                <!-- Add this section to display saved trees -->
                <div v-if="savedTrees.length > 0" class="mt-4">
                    <h3>Saved Family Trees</h3>
                    <div v-for="tree in savedTrees" :key="tree.id" class="mb-2 p-2 border rounded">
                        <div class="flex justify-between items-center">
                            <h4>{{ tree.name }}</h4>
                            <button 
                                @click="loadTree(tree)"
                                class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
                            >
                                Load
                            </button>
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
import axios from 'axios'

export default {
    components: {
        FamilyTree
    },
    props: {
        errors: Object,
        auth: Object,
        csrf_token: String
    },
    
    data() {
        return {
            gedcomData: null,
            treeData: null,
            savedTrees: [],
            isTreeVisible: false,
            parsedIndividuals: [],
            parsedFamilies: [],
            processedPeople: new Map(),
            // ... other data properties
        }
    },

    methods: {
        loadTree(tree) {
            try {
                console.log('Loading tree:', tree);
                
                // The data is already a string, no need to parse
                const gedcomData = tree.data;
                console.log('GEDCOM data type:', typeof gedcomData);
                console.log('GEDCOM data first 100 chars:', gedcomData.substring(0, 100));
                
                // Update the GEDCOM data
                this.gedcomData = gedcomData;
                
                // Parse and process the GEDCOM data
                this.parseGEDCOM(gedcomData);
                
                // Clear existing tree data
                this.treeData = null;
                
                // Process the first person (root) in the tree
                const firstPerson = this.parsedIndividuals[0];
                if (firstPerson) {
                    const personId = firstPerson[0];
                    this.processPersonAndAncestors(personId);
                }
                
                // Render the tree
                this.renderTree();
            } catch (error) {
                console.error('Error loading tree:', error);
                console.error('GEDCOM data that caused error:', tree.data);
            }
        },

        parseGEDCOM(gedcomData) {
            if (!gedcomData) {
                console.error('No GEDCOM data provided');
                return;
            }

            console.log('Starting GEDCOM parsing');
            // Reset arrays
            this.parsedIndividuals = [];
            this.parsedFamilies = [];
            this.processedPeople.clear();
            
            try {
                // Split the GEDCOM data into lines
                const lines = gedcomData.split('\n');
                
                let currentRecord = null;
                let currentData = [];
                
                // Process each line
                lines.forEach(line => {
                    if (!line.trim()) return; // Skip empty lines
                    
                    const [level, ...rest] = line.trim().split(' ');
                    const tag = rest[0];
                    
                    if (level === '0') {
                        if (currentRecord) {
                            if (currentRecord === 'INDI') {
                                this.parsedIndividuals.push(currentData);
                            } else if (currentRecord === 'FAM') {
                                this.parsedFamilies.push(currentData);
                            }
                        }
                        currentData = [rest.join(' ')];
                        currentRecord = rest[rest.length - 1];
                    } else {
                        currentData.push(rest.join(' '));
                    }
                });
                
                // Add the last record
                if (currentRecord === 'INDI') {
                    this.parsedIndividuals.push(currentData);
                } else if (currentRecord === 'FAM') {
                    this.parsedFamilies.push(currentData);
                }
                
                console.log('Parsed Individuals:', this.parsedIndividuals);
                console.log('Parsed Families:', this.parsedFamilies);
            } catch (error) {
                console.error('Error parsing GEDCOM:', error);
                console.error('GEDCOM data that caused error:', gedcomData);
            }
        },

        processPersonAndAncestors(personId) {
            console.log('Processing person:', personId);
            if (this.processedPeople.has(personId)) {
                console.log('Already processed:', personId);
                return this.processedPeople.get(personId);
            }

            const person = this.findPerson(personId);
            if (!person) return null;

            const node = this.buildPersonNode(person);
            this.processedPeople.set(personId, node);

            // Process parent family
            const parentFamilyId = this.findParentFamily(personId);
            if (parentFamilyId) {
                console.log('Processing parent family:', parentFamilyId);
                const family = this.findFamily(parentFamilyId);
                if (family) {
                    const fatherId = this.findFather(family);
                    if (fatherId) {
                        console.log('Adding father:', fatherId);
                        node.children.push(this.processPersonAndAncestors(fatherId));
                    }
                    const motherId = this.findMother(family);
                    if (motherId) {
                        console.log('Adding mother:', motherId);
                        node.children.push(this.processPersonAndAncestors(motherId));
                    }
                }
            }

            // Process own family
            const ownFamilyIds = this.findOwnFamilies(personId);
            ownFamilyIds.forEach(familyId => {
                console.log('Processing own family:', familyId);
                const family = this.findFamily(familyId);
                if (family) {
                    const childIds = this.findChildren(family);
                    childIds.forEach(childId => {
                        console.log('Adding child:', childId);
                        const childNode = this.processPersonAndAncestors(childId);
                        if (childNode) {
                            node.children.push(childNode);
                        }
                    });
                }
            });

            console.log('Finished node', node.name, 'with', node.children.length, 'children');
            return node;
        },

        async loadSavedTrees() {
            try {
                console.log('Fetching saved trees...');
                
                const response = await axios.get('/api/family-trees', {
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                console.log('Raw response:', response);

                if (response.data.success) {
                    this.savedTrees = response.data.trees.map(tree => ({
                        id: tree.id,
                        name: tree.name,
                        data: tree.data,
                        members: {
                            husbands: tree.members.husbands || [],
                            wives: tree.members.wives || [],
                            children: tree.members.children || [],
                            others: tree.members.others || []
                        }
                    }));
                    console.log('Loaded trees:', this.savedTrees);
                } else {
                    console.error('Server returned error:', response.data);
                    throw new Error(response.data.message || 'Failed to load trees');
                }
            } catch (error) {
                console.error('Failed to load saved trees:', error);
                if (error.response?.data) {
                    console.error('Error response:', error.response.data);
                }
                throw new Error('Failed to load trees');
            }
        },

        toggleTreeVisibility() {
            this.isTreeVisible = !this.isTreeVisible;
            if (this.isTreeVisible && this.treeData) {
                this.$nextTick(() => {
                    this.renderTree();
                });
            }
        },

        async saveToDatabase() {
            try {
                console.log('GEDCOM Data Type:', typeof this.gedcomData);
                console.log('GEDCOM Data Length:', this.gedcomData?.length);
                console.log('GEDCOM Data Preview:', this.gedcomData?.substring(0, 200));

                const response = await axios.post('/api/store-family-tree', {
                    gedcomData: this.gedcomData
                }, {
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    }
                });

                console.log('Full server response:', response);

                if (response.data.success) {
                    console.log('Family tree saved successfully');
                    await this.loadSavedTrees();
                } else {
                    console.error('Server returned error:', response.data);
                    throw new Error(response.data.message || 'Failed to save');
                }
            } catch (error) {
                console.error('Full error object:', error);
                console.error('Error response:', error.response?.data);
                console.error('Error status:', error.response?.status);
                console.error('Error headers:', error.response?.headers);
                throw new Error(error.response?.data?.message || 'Failed to save');
            }
        },

        findPerson(gedcomId) {
            const lines = this.gedcomData.split('\n');
            let person = null;
            let currentPerson = null;

            for (const line of lines) {
                const [level, tag, ...rest] = line.trim().split(' ');
                const value = rest.join(' ');

                if (level === '0' && value.includes('INDI')) {
                    const id = tag.replace(/@/g, '');
                    if (currentPerson && currentPerson.id === gedcomId) {
                        person = currentPerson;
                        break;
                    }
                    currentPerson = { id, name: '', birth: '', death: '', sex: '' };
                } else if (currentPerson) {
                    if (tag === 'NAME') {
                        currentPerson.name = value.replace(/\//g, '');
                    } else if (tag === 'SEX') {
                        currentPerson.sex = value;
                    } else if (level === '2' && tag === 'DATE') {
                        if (lines[lines.indexOf(line) - 1].includes('BIRT')) {
                            currentPerson.birth = value;
                        } else if (lines[lines.indexOf(line) - 1].includes('DEAT')) {
                            currentPerson.death = value;
                        }
                    }
                }
            }

            return person;
        },

        findParents(gedcomId) {
            const lines = this.gedcomData.split('\n');
            let parents = { father: null, mother: null };
            let familyId = null;

            // First find the family where this person is a child
            for (const line of lines) {
                const [level, tag, ...rest] = line.trim().split(' ');
                const value = rest.join(' ');

                if (level === '1' && tag === 'FAMC') {
                    const personLines = this.findPersonLines(gedcomId);
                    if (personLines && personLines.includes(line)) {
                        familyId = value.replace(/@/g, '');
                        break;
                    }
                }
            }

            if (familyId) {
                let inFamily = false;
                for (const line of lines) {
                    const [level, tag, ...rest] = line.trim().split(' ');
                    const value = rest.join(' ');

                    if (level === '0' && value.includes('FAM')) {
                        inFamily = tag.replace(/@/g, '') === familyId;
                    } else if (inFamily && level === '1') {
                        if (tag === 'HUSB') {
                            parents.father = value.replace(/@/g, '');
                        } else if (tag === 'WIFE') {
                            parents.mother = value.replace(/@/g, '');
                        }
                    }
                }
            }

            return parents;
        },

        findPersonLines(gedcomId) {
            const lines = this.gedcomData.split('\n');
            let personLines = [];
            let inPerson = false;

            for (const line of lines) {
                const [level, tag, ...rest] = line.trim().split(' ');
                const value = rest.join(' ');

                if (level === '0') {
                    if (value.includes('INDI')) {
                        inPerson = tag.replace(/@/g, '') === gedcomId;
                    } else {
                        inPerson = false;
                    }
                }

                if (inPerson) {
                    personLines.push(line);
                }
            }

            return personLines;
        },

        processPersonAndAncestors(gedcomId, level = 0) {
            if (!gedcomId || level > 10) return null; // Prevent infinite recursion

            const person = this.findPerson(gedcomId);
            if (!person) return null;

            const parents = this.findParents(gedcomId);
            const node = {
                id: person.id,
                name: person.name,
                birth: person.birth,
                death: person.death,
                sex: person.sex,
                level: level
            };

            if (parents.father) {
                node.father = this.processPersonAndAncestors(parents.father, level + 1);
            }
            if (parents.mother) {
                node.mother = this.processPersonAndAncestors(parents.mother, level + 1);
            }

            return node;
        },

        async loadTree(gedcomData) {
            try {
                console.log('Loading tree with GEDCOM data...');
                this.gedcomData = gedcomData;

                // Find the first person (usually the main person)
                const lines = gedcomData.split('\n');
                let mainPersonId = null;

                for (const line of lines) {
                    const [level, tag, ...rest] = line.trim().split(' ');
                    const value = rest.join(' ');

                    if (level === '0' && value.includes('INDI')) {
                        mainPersonId = tag.replace(/@/g, '');
                        break;
                    }
                }

                if (!mainPersonId) {
                    throw new Error('No individuals found in GEDCOM data');
                }

                const processedData = this.processPersonAndAncestors(mainPersonId);
                if (!processedData) {
                    throw new Error('Failed to process family tree data');
                }

                this.treeData = processedData;
                console.log('Tree loaded successfully:', this.treeData);
            } catch (error) {
                console.error('Error loading tree:', error);
                console.log('GEDCOM data that caused error:', gedcomData);
                throw error;
            }
        }
    },

    mounted() {
        console.log('Component mounted, loading saved trees...');
        this.loadSavedTrees();
    }
}
</script>

<style>
.tree-container {
    width: 100%;
    height: 600px; /* Adjust as needed */
    border: 1px solid #ccc;
    margin-top: 20px;
}
</style> 