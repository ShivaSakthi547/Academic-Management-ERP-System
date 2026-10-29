/**
 * export_utils.js
 * Reusable export helpers for the Academic Management System.
 *
 * Depends on (loaded by the calling page):
 *   - SheetJS  : https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js
 *   - jsPDF    : https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js
 *   - autotable: https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js
 */

/**
 * Returns a cleaned clone of a <table> element:
 *   - Removes the last column (Actions)
 *   - Replaces <img> cells with empty strings
 *   - Strips HTML badges/spans — keeps only text content
 *
 * @param {string} tableId  - The id attribute of the source <table>
 * @returns {HTMLTableElement|null}
 */
function _getCleanTable(tableId) {
    const original = document.getElementById(tableId);
    if (!original) {
        console.error('[export_utils] Table not found: #' + tableId);
        return null;
    }

    // Deep-clone so we don't touch the live DOM
    const clone = original.cloneNode(true);

    // ── Detect columns that should be excluded ──────────────────────────────
    // We always drop the LAST column (Actions).
    const headerRow = clone.querySelector('thead tr');
    const totalCols = headerRow ? headerRow.children.length : 0;

    // Find indices of columns containing only images (e.g. "Photo")
    const imgColIndices = new Set();
    if (headerRow) {
        Array.from(headerRow.children).forEach((th, idx) => {
            const text = th.textContent.trim().toLowerCase();
            if (text === 'photo' || text === 'image' || text === 'picture') {
                imgColIndices.add(idx);
            }
        });
    }

    // Columns to remove = last col (Actions) + any photo columns
    const dropIndices = new Set([...imgColIndices, totalCols - 1]);

    clone.querySelectorAll('tr').forEach(row => {
        const cells = Array.from(row.children);

        // Process cells in reverse so index shifting doesn't affect us
        for (let i = cells.length - 1; i >= 0; i--) {
            if (dropIndices.has(i)) {
                cells[i].remove();
                continue;
            }
            // Flatten cell to plain text (strips badges, icons, nested HTML)
            cells[i].textContent = cells[i].textContent.trim();
        }
    });

    return clone;
}

/**
 * Export a table to an Excel (.xlsx) file using SheetJS.
 *
 * @param {string} tableId  - The id attribute of the <table> to export
 * @param {string} filename - Base filename WITHOUT extension (e.g. 'students_data')
 */
function exportToExcel(tableId, filename) {
    if (typeof XLSX === 'undefined') {
        alert('Excel library is not loaded yet. Please wait a moment and try again.');
        return;
    }

    const cleanTable = _getCleanTable(tableId);
    if (!cleanTable) return;

    try {
        const wb = XLSX.utils.table_to_book(cleanTable, { sheet: 'Data' });
        XLSX.writeFile(wb, filename + '.xlsx');
    } catch (e) {
        console.error('[export_utils] Excel export failed:', e);
        alert('Excel export failed. Please check the console for details.');
    }
}

/**
 * Export a table to a PDF file using jsPDF + autotable.
 *
 * @param {string} tableId  - The id attribute of the <table> to export
 * @param {string} filename - Base filename WITHOUT extension (e.g. 'students_data')
 * @param {string} title    - Human-readable heading printed at top of the PDF
 */
function exportToPDF(tableId, filename, title) {
    if (typeof window.jspdf === 'undefined' || typeof window.jspdf.jsPDF === 'undefined') {
        alert('PDF library is not loaded yet. Please wait a moment and try again.');
        return;
    }

    const cleanTable = _getCleanTable(tableId);
    if (!cleanTable) return;

    try {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ orientation: 'landscape', unit: 'pt', format: 'a4' });

        // ── Header ─────────────────────────────────────────────────────────
        const pageWidth = doc.internal.pageSize.getWidth();

        doc.setFontSize(18);
        doc.setTextColor(13, 110, 253); // Bootstrap primary blue
        doc.setFont('helvetica', 'bold');
        doc.text(title, pageWidth / 2, 40, { align: 'center' });

        // Timestamp subtitle
        const now = new Date().toLocaleString('en-IN', {
            day: '2-digit', month: 'short', year: 'numeric',
            hour: '2-digit', minute: '2-digit'
        });
        doc.setFontSize(9);
        doc.setTextColor(108, 117, 125); // Bootstrap muted
        doc.setFont('helvetica', 'normal');
        doc.text('Exported on: ' + now, pageWidth / 2, 56, { align: 'center' });

        // ── Table ──────────────────────────────────────────────────────────
        doc.autoTable({
            html: cleanTable,
            startY: 70,
            styles: {
                font: 'helvetica',
                fontSize: 9,
                cellPadding: 5,
                overflow: 'linebreak',
            },
            headStyles: {
                fillColor: [13, 110, 253],   // Bootstrap primary
                textColor: 255,
                fontStyle: 'bold',
                fontSize: 9,
                halign: 'center',
            },
            alternateRowStyles: {
                fillColor: [248, 249, 250],   // Bootstrap light
            },
            bodyStyles: {
                textColor: [33, 37, 41],      // Bootstrap dark
            },
            margin: { top: 70, right: 20, bottom: 20, left: 20 },
            tableWidth: 'auto',
        });

        // ── Footer — page numbers ──────────────────────────────────────────
        const pageCount = doc.internal.getNumberOfPages();
        for (let i = 1; i <= pageCount; i++) {
            doc.setPage(i);
            doc.setFontSize(8);
            doc.setTextColor(150);
            doc.text(
                'Page ' + i + ' of ' + pageCount,
                pageWidth / 2,
                doc.internal.pageSize.getHeight() - 10,
                { align: 'center' }
            );
        }

        doc.save(filename + '.pdf');
    } catch (e) {
        console.error('[export_utils] PDF export failed:', e);
        alert('PDF export failed. Please check the console for details.');
    }
}
