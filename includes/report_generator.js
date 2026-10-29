// includes/report_generator.js

let currentReportData = [];
let currentReportHeaders = [];
let currentModule = '';

async function generateReport() {
    const form = document.getElementById('reportFilterForm');
    const formData = new FormData(form);
    const btnSubmit = form.querySelector('.btn-primary');
    
    // Save original text
    const originalText = btnSubmit.innerHTML;
    btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Generating...';
    btnSubmit.disabled = true;

    try {
        const response = await fetch('ajax_report_handler.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.status === 'success') {
            currentReportData = result.data;
            currentModule = formData.get('module');
            renderReportTable();
        } else {
            alert('Error generating report: ' + result.message);
        }
    } catch (error) {
        console.error('Error fetching report:', error);
        alert('Failed to generate report. Please try again.');
    } finally {
        btnSubmit.innerHTML = originalText;
        btnSubmit.disabled = false;
    }
}

function renderReportTable() {
    const thead = document.getElementById('reportTableHead');
    const tbody = document.getElementById('reportTableBody');
    const btnExcel = document.getElementById('btnReportExcel');
    const btnPdf = document.getElementById('btnReportPdf');
    
    thead.innerHTML = '';
    tbody.innerHTML = '';

    if (!currentReportData || currentReportData.length === 0) {
        tbody.innerHTML = '<tr><td class="text-center text-muted" colspan="100%">No Records Found</td></tr>';
        btnExcel.disabled = true;
        btnPdf.disabled = true;
        return;
    }

    // Extract headers
    currentReportHeaders = Object.keys(currentReportData[0]);
    
    // Render thead
    let trHead = '<tr>';
    currentReportHeaders.forEach(header => {
        trHead += `<th>${header}</th>`;
    });
    trHead += '</tr>';
    thead.innerHTML = trHead;

    // Render tbody
    let tbodyHtml = '';
    currentReportData.forEach(row => {
        tbodyHtml += '<tr>';
        currentReportHeaders.forEach(header => {
            const v = row[header];
            tbodyHtml += `<td>${(v !== null && v !== undefined) ? v : ''}</td>`;
        });
        tbodyHtml += '</tr>';
    });
    tbody.innerHTML = tbodyHtml;

    btnExcel.disabled = false;
    btnPdf.disabled = false;
}

function getFilterSummary() {
    const form = document.getElementById('reportFilterForm');
    const formData = new FormData(form);
    let filters = [];
    for (let [key, value] of formData.entries()) {
        if (key !== 'module' && value.trim() !== '') {
            filters.push(`${key.replace('_', ' ').toUpperCase()}: ${value}`);
        }
    }
    return filters.join(' | ') || 'None';
}

function exportReportToExcel() {
    if (currentReportData.length === 0) return;
    
    const ws_name = "ReportData";
    const wb = XLSX.utils.book_new();
    
    // Prepare data with title and filters
    let ws_data = [
        [`Academic Management System - ${currentModule.toUpperCase()} REPORT`],
        [`Filters Applied: ${getFilterSummary()}`],
        [`Generated On: ${new Date().toLocaleString()}`],
        [],
        currentReportHeaders
    ];
    
    currentReportData.forEach(row => {
        let rowData = currentReportHeaders.map(header => row[header]);
        ws_data.push(rowData);
    });

    const ws = XLSX.utils.aoa_to_sheet(ws_data);
    XLSX.utils.book_append_sheet(wb, ws, ws_name);
    
    // Auto-size columns logic (basic)
    const wscols = currentReportHeaders.map(h => ({ wch: Math.max(h.length, 15) }));
    ws['!cols'] = wscols;

    XLSX.writeFile(wb, `${currentModule}_report_${new Date().getTime()}.xlsx`);
}

function exportReportToPDF() {
    if (currentReportData.length === 0) return;
    
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('l', 'mm', 'a4'); // Landscape for reports
    
    const title = `Academic Management System - ${currentModule.toUpperCase()} REPORT`;
    const filtersStr = `Filters Applied: ${getFilterSummary()}`;
    const generatedStr = `Generated On: ${new Date().toLocaleString()}`;

    // Header
    doc.setFontSize(16);
    doc.setTextColor(40);
    doc.text(title, 14, 20);
    
    doc.setFontSize(10);
    doc.setTextColor(100);
    doc.text(filtersStr, 14, 28);
    doc.text(generatedStr, 14, 34);

    // Prepare table data
    const tableBody = currentReportData.map(row =>
        currentReportHeaders.map(header => {
            const v = row[header];
            return (v !== null && v !== undefined) ? String(v) : '';
        })
    );

    // AutoTable
    doc.autoTable({
        head: [currentReportHeaders],
        body: tableBody,
        startY: 40,
        theme: 'grid',
        styles: { fontSize: 8, cellPadding: 2 },
        headStyles: { fillColor: [41, 128, 185], textColor: 255 },
        didDrawPage: function (data) {
            // Footer
            let str = 'Page ' + doc.internal.getNumberOfPages();
            doc.setFontSize(8);
            doc.text(str, data.settings.margin.left, doc.internal.pageSize.height - 10);
        }
    });

    doc.save(`${currentModule}_report_${new Date().getTime()}.pdf`);
}
