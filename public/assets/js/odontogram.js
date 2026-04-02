// Odontogram functionality
document.addEventListener('DOMContentLoaded', function() { initOdontogram(); });
function initOdontogram() {
    const toothGrid = document.getElementById('tooth-grid');
    if (!toothGrid) return;
    toothGrid.innerHTML = '';
    const teeth = [];
    for (let i = 1; i <= 32; i++) { teeth.push({ label: i.toString(), number: i }); }
    const quadrants = [
        { label: 'Upper Right (1-8)', start: 0, end: 8 },
        { label: 'Upper Left (9-16)', start: 8, end: 16 },
        { label: 'Lower Left (17-24)', start: 16, end: 24 },
        { label: 'Lower Right (25-32)', start: 24, end: 32 }
    ];
    quadrants.forEach((quadrant, qIndex) => {
        const quadrantLabel = document.createElement('div');
        quadrantLabel.className = 'quadrant-label';
        quadrantLabel.textContent = quadrant.label;
        toothGrid.appendChild(quadrantLabel);
        for (let i = quadrant.start; i < quadrant.end; i++) {
            const tooth = teeth[i];
            const toothButton = document.createElement('button');
            toothButton.type = 'button';
            toothButton.className = 'tooth-button';
            toothButton.dataset.toothNumber = tooth.number;
            toothButton.dataset.toothLabel = tooth.label;
            toothButton.innerHTML = `<div class="tooth-number">${tooth.label}</div><div class="tooth-fdi">${tooth.number}</div>`;
            toothButton.addEventListener('click', function() { toggleToothSelection(tooth.number); });
            toothGrid.appendChild(toothButton);
        }
    });
    if (!window.selectedTeeth) { window.selectedTeeth = []; }
    setupSurfaceCheckboxes();
    updateSelectedTeethDisplay();
}
function toggleToothSelection(toothNumber) {
    const toothButton = document.querySelector(`.tooth-button[data-tooth-number="${toothNumber}"]`);
    const index = window.selectedTeeth.indexOf(toothNumber);
    if (index === -1) {
        window.selectedTeeth.push(toothNumber);
        toothButton.classList.add('selected');
    } else {
        window.selectedTeeth.splice(index, 1);
        toothButton.classList.remove('selected');
    }
    updateSelectedTeethDisplay();
}
function updateSelectedTeethDisplay() {
    const selectedTeethList = document.getElementById('selected-teeth-list');
    const toothNumbersInput = document.getElementById('record-tooth-numbers');
    if (!selectedTeethList || !toothNumbersInput) return;
    window.selectedTeeth.sort((a, b) => a - b);
    if (window.selectedTeeth.length === 0) {
        selectedTeethList.innerHTML = '<span style="color: #6c757d;">None</span>';
        toothNumbersInput.value = '';
    } else {
        selectedTeethList.innerHTML = window.selectedTeeth.map(toothNumber => `
            <div class="selected-tooth-chip">${toothNumber}<button type="button" class="remove-tooth" onclick="removeTooth(${toothNumber})"><i class="fas fa-times"></i></button></div>`).join('');
        toothNumbersInput.value = JSON.stringify(window.selectedTeeth);
    }
}
function removeTooth(toothNumber) {
    const index = window.selectedTeeth.indexOf(toothNumber);
    if (index !== -1) {
        window.selectedTeeth.splice(index, 1);
        const toothButton = document.querySelector(`.tooth-button[data-tooth-number="${toothNumber}"]`);
        if (toothButton) { toothButton.classList.remove('selected'); }
        updateSelectedTeethDisplay();
    }
}
function setupSurfaceCheckboxes() {
    const surfaceCheckboxes = document.querySelectorAll('input[name="surfaces"]');
    const surfacesInput = document.getElementById('record-surfaces');
    if (!surfaceCheckboxes.length || !surfacesInput) return;
    surfaceCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() { updateSurfacesInput(); });
    });
    updateSurfacesInput();
}
function updateSurfacesInput() {
    const surfaceCheckboxes = document.querySelectorAll('input[name="surfaces"]:checked');
    const surfacesInput = document.getElementById('record-surfaces');
    if (!surfacesInput) return;
    const selectedSurfaces = Array.from(surfaceCheckboxes).map(checkbox => checkbox.value);
    surfacesInput.value = JSON.stringify(selectedSurfaces);
}
function loadOdontogramData(toothNumbers, surfaces) {
    window.selectedTeeth = [];
    document.querySelectorAll('.tooth-button.selected').forEach(btn => { btn.classList.remove('selected'); });
    document.querySelectorAll('input[name="surfaces"]').forEach(checkbox => { checkbox.checked = false; });
    let parsedToothNumbers = [];
    try {
        if (toothNumbers && toothNumbers !== '[]' && toothNumbers.trim() !== '') {
            parsedToothNumbers = JSON.parse(toothNumbers);
            if (Array.isArray(parsedToothNumbers)) {
                window.selectedTeeth = parsedToothNumbers;
                parsedToothNumbers.forEach(toothNumber => {
                    const toothButton = document.querySelector(`.tooth-button[data-tooth-number="${toothNumber}"]`);
                    if (toothButton) { toothButton.classList.add('selected'); }
                });
            }
        }
    } catch (e) {
        console.error('Error parsing tooth numbers:', e);
        if (toothNumbers && toothNumbers.startsWith('[')) {
            try {
                parsedToothNumbers = JSON.parse(toothNumbers.replace(/'/g, '"'));
                if (Array.isArray(parsedToothNumbers)) {
                    window.selectedTeeth = parsedToothNumbers;
                    parsedToothNumbers.forEach(toothNumber => {
                        const toothButton = document.querySelector(`.tooth-button[data-tooth-number="${toothNumber}"]`);
                        if (toothButton) { toothButton.classList.add('selected'); }
                    });
                }
            } catch (e2) { console.error('Alternative parsing also failed:', e2); }
        }
    }
    let parsedSurfaces = [];
    try {
        if (surfaces && surfaces !== '[]' && surfaces.trim() !== '') {
            parsedSurfaces = JSON.parse(surfaces);
            if (Array.isArray(parsedSurfaces)) {
                document.querySelectorAll('input[name="surfaces"]').forEach(checkbox => {
                    checkbox.checked = parsedSurfaces.includes(checkbox.value);
                });
            }
        }
    } catch (e) { console.error('Error parsing surfaces:', e); }
    updateSelectedTeethDisplay();
    updateSurfacesInput();
}
function clearOdontogramData() {
    window.selectedTeeth = [];
    document.querySelectorAll('.tooth-button.selected').forEach(btn => { btn.classList.remove('selected'); });
    document.querySelectorAll('input[name="surfaces"]').forEach(checkbox => { checkbox.checked = false; });
    updateSelectedTeethDisplay();
    updateSurfacesInput();
}
function getSelectedToothNumbers() { return window.selectedTeeth || []; }
function getSelectedSurfaces() {
    const surfaceCheckboxes = document.querySelectorAll('input[name="surfaces"]:checked');
    return Array.from(surfaceCheckboxes).map(checkbox => checkbox.value);
}
function resetOdontogramSelections() { clearOdontogramData(); }