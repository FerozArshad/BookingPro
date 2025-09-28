/**
 * Optimized BookingPro Google Sheets Integration
 * Unified approach using Webhook_Log sheet for all activity tracking
 */

/**
 * Configuration
 */
const CONFIG = {
  MAIN_SHEET_NAME: 'Bookings Website',
  WEBHOOK_LOG_SHEET: 'Webhook_Log',
  MAX_LOG_ROWS: 1000,
  TIMEOUT_MS: 30000
};

/**
 * Sheet headers - Updated to match actual Google Sheet structure
 */
const BOOKING_HEADERS = [
  'Timestamp', 'Lead Type', 'Status', 'Booking ID', 'Customer Name', 'Customer Email', 'Customer Phone',
  'Customer Address', 'ZIP Code', 'City', 'State', 'Service', 'Company', 'Date', 'Time',
  'UTM Source', 'UTM Medium', 'UTM Campaign', 'UTM Term', 'UTM Content', 'GCLID', 'Referrer',
  'Roof Action', 'Roof Material', 'Windows Action', 'Windows Replace Qty', 'Windows Repair Needed',
  'Bathroom Option', 'Siding Option', 'Siding Material', 'Kitchen Action', 'Kitchen Component',
  'Decks Action', 'Decks Material', 'ADU ZIP', 'ADU Action', 'ADU Type', 'Specifications',
  'Session ID', 'Form Step', 'Completion %', 'Lead Score', 'Conversion Time', 'Created Date', 'Last Updated', 'Notes'
];

const WEBHOOK_LOG_HEADERS = [
  'Timestamp', 'Event Type', 'Status', 'Session ID', 'Customer Name', 'Service',
  'Data Received', 'Processing Result', 'Error Message', 'Response Time'
];

/**
 * Merge existing row data with new data (don't overwrite good data with empty values)
 */
function mergeRowData(existingData, newData, headers) {
  const merged = [...existingData]; // Copy existing data
  
  for (let i = 0; i < newData.length && i < merged.length; i++) {
    const header = headers[i];
    const existingValue = merged[i];
    const newValue = newData[i];
    
    // Only update if new value is not empty and different from existing
    if (newValue && newValue.toString().trim() !== '' && newValue !== existingValue) {
      // Special handling for certain fields
      if (header === 'Timestamp') {
        merged[i] = newValue; // Always update timestamp
      } else if (header === 'Lead Score' && parseInt(newValue) > parseInt(existingValue || 0)) {
        merged[i] = newValue; // Update if score improved
      } else if (!existingValue || existingValue.toString().trim() === '') {
        merged[i] = newValue; // Fill empty fields
      } else if (['Customer Name', 'Customer Email', 'Customer Phone'].includes(header)) {
        merged[i] = newValue; // Always update customer info
      }
    }
  }
  
  return merged;
}

/**
 * Parse URL-encoded form data
 */
function parseUrlEncoded(dataString) {
  const params = {};
  const pairs = dataString.split('&');
  
  for (let i = 0; i < pairs.length; i++) {
    const pair = pairs[i].split('=');
    if (pair.length === 2) {
      const key = decodeURIComponent(pair[0]);
      const value = decodeURIComponent(pair[1].replace(/\+/g, ' '));
      params[key] = value;
    }
  }
  
  return params;
}

/**
 * Main webhook entry point with comprehensive logging
 */
function doPost(e) {
  const startTime = Date.now();
  let data = {};
  let parseSuccess = false;
  
  // Initial logging - webhook received
  try {
    const logSheet = getOrCreateLogSheet();
    const initialLog = [
      Utilities.formatDate(new Date(), Session.getScriptTimeZone(), "MM/dd/yyyy HH:mm:ss"),
      'Webhook Received',
      'processing',
      'initial',
      'System',
      'Webhook',
      'Raw webhook data received',
      'Starting processing...',
      '',
      '0ms'
    ];
    logSheet.appendRow(initialLog);
  } catch (logError) {
    console.error('Failed to log webhook receipt:', logError);
  }
  
  try {
    // Parse incoming data with enhanced URL-encoded support
    if (e.postData && e.postData.contents) {
      try {
        // First try JSON parsing
        data = JSON.parse(e.postData.contents);
        parseSuccess = true;
        console.log('Successfully parsed JSON data');
      } catch (parseError) {
        // If JSON fails, try URL-encoded parsing
        try {
          data = parseUrlEncoded(e.postData.contents);
          parseSuccess = true;
          console.log('Successfully parsed URL-encoded data');
        } catch (urlError) {
          data = { raw_data: e.postData.contents, parse_error: parseError.toString() };
          console.log('Failed to parse both JSON and URL-encoded, using raw data');
        }
      }
    } else if (e.parameter) {
      data = e.parameter;
      parseSuccess = true;
      console.log('Using parameter data');
    } else {
      data = { error: 'No data received in webhook' };
      console.log('No data found in webhook');
    }
    
    // Log the parsed data with more detail
    const sessionForLog = data.session_id || 'no-session';
    const customerForLog = data.customer_name || data.name || 'Anonymous';
    const serviceForLog = data.service || data.service_type || 'Unknown';
    
    const dataLog = [
      Utilities.formatDate(new Date(), Session.getScriptTimeZone(), "MM/dd/yyyy HH:mm:ss"),
      'Data Parsed',
      parseSuccess ? 'success' : 'warning',
      sessionForLog,
      customerForLog,
      serviceForLog,
      JSON.stringify(data).substring(0, 500),
      parseSuccess ? `Parsed: session=${sessionForLog}, customer=${customerForLog}, service=${serviceForLog}` : 'Parse failed, using fallback',
      parseSuccess ? '' : 'JSON parse error occurred',
      `${Date.now() - startTime}ms`
    ];
    
    try {
      const logSheet = getOrCreateLogSheet();
      logSheet.appendRow(dataLog);
    } catch (logError) {
      console.error('Failed to log data parsing:', logError);
    }
    
    // Process the webhook data
    const result = processWebhookData(data, true);
    
    // Log successful processing
    const responseTime = Date.now() - startTime;
    logWebhookActivity({
      timestamp: new Date(),
      eventType: result.isComplete ? 'Complete Booking' : 'Incomplete Lead',
      status: 'success',
      sessionId: result.sessionId || 'unknown',
      customerName: data.customer_name || data.name || 'Anonymous',
      service: data.service || data.service_type || 'Unknown',
      dataReceived: JSON.stringify(data).substring(0, 500),
      processingResult: JSON.stringify(result).substring(0, 200),
      errorMessage: '',
      responseTime: responseTime
    });
    
    return ContentService
      .createTextOutput(JSON.stringify({ success: true, result: result }))
      .setMimeType(ContentService.MimeType.JSON);
      
  } catch (error) {
    // Log error with full context
    const responseTime = Date.now() - startTime;
    logWebhookActivity({
      timestamp: new Date(),
      eventType: 'Processing Error',
      status: 'error',
      sessionId: data?.session_id || 'unknown',
      customerName: data?.customer_name || 'Unknown',
      service: data?.service || 'Unknown',
      dataReceived: JSON.stringify(data || {}).substring(0, 500),
      processingResult: '',
      errorMessage: error.toString(),
      responseTime: responseTime
    });
    
    console.error('Webhook processing error:', error);
    
    return ContentService
      .createTextOutput(JSON.stringify({ success: false, error: error.toString() }))
      .setMimeType(ContentService.MimeType.JSON);
  }
}

/**
 * Process webhook data - unified approach
 */
function processWebhookData(data, logActivity = false) {
  if (!data || typeof data !== 'object') {
    throw new Error('Invalid data received');
  }
  
  // Use existing session ID from WordPress or generate new one
  let sessionId = data.session_id;
  if (!sessionId || sessionId === 'no-session' || sessionId === 'unknown') {
    sessionId = generateSessionId();
  }
  
  // Analyze lead completion
  const leadAnalysis = analyzeLead(data);
  
  // Get or create main sheet
  const mainSheet = getOrCreateMainSheet();
  
  // Check for existing entry by session ID
  const existingRow = findExistingEntry(mainSheet, sessionId);
  
  // Prepare row data
  const rowData = BOOKING_HEADERS.map(header => {
    return getFieldValue(header, data, leadAnalysis, sessionId);
  });
  
  let rowNumber;
  if (existingRow > 0) {
    // Update existing row - merge data, don't overwrite good data with empty values
    const existingData = mainSheet.getRange(existingRow, 1, 1, BOOKING_HEADERS.length).getValues()[0];
    const mergedData = mergeRowData(existingData, rowData, BOOKING_HEADERS);
    
    mainSheet.getRange(existingRow, 1, 1, mergedData.length).setValues([mergedData]);
    rowNumber = existingRow;
    
    // Highlight updated row
    mainSheet.getRange(rowNumber, 1, 1, BOOKING_HEADERS.length).setBackground("#fff2cc");
  } else {
    // Add new row
    mainSheet.appendRow(rowData);
    rowNumber = mainSheet.getLastRow();
    
    // Highlight new row based on completion
    const bgColor = leadAnalysis.isComplete ? "#d9ead3" : "#fce5cd";
    mainSheet.getRange(rowNumber, 1, 1, BOOKING_HEADERS.length).setBackground(bgColor);
  }
  
  return {
    sessionId: sessionId,
    rowNumber: rowNumber,
    action: existingRow > 0 ? 'updated' : 'created',
    leadType: leadAnalysis.leadType,
    status: leadAnalysis.status,
    completionPercentage: leadAnalysis.completionPercentage,
    isComplete: leadAnalysis.isComplete
  };
}

/**
 * Analyze lead completion and status
 */
function analyzeLead(data) {
  const hasBookingId = !!(data.booking_id || data.id);
  const hasCompleteCustomerInfo = !!(data.customer_name && data.customer_email && data.customer_phone);
  const hasAppointmentInfo = !!(data.booking_date || data.selected_date || data.date) && (data.booking_time || data.selected_time || data.time);
  const hasServiceInfo = !!(data.service_type || data.service);
  
  // CRITICAL FIX: Check if this is explicitly marked as a complete booking
  const isCompleteBookingAction = data.action === 'complete_booking';
  const isCompleteLeadStatus = data.lead_status === 'Complete' || data.lead_status === 'Converted';
  
  // Calculate completion percentage
  const requiredFields = [
    'service', 'customer_name', 'customer_email', 'customer_phone', 
    'customer_address', 'booking_date', 'booking_time', 'company'
  ];
  
  const completedFields = requiredFields.filter(field => {
    const value = data[field] || data[field.replace('customer_', '')] || 
                   data[field.replace('booking_', 'selected_')] || 
                   data[field.replace('booking_', '')];
    return value && value.toString().trim() !== '';
  }).length;
  
  const completionPercentage = Math.round((completedFields / requiredFields.length) * 100);
  
  // CRITICAL FIX: Determine lead type and status with priority logic
  let leadType, status;
  
  // Priority 1: Check explicit complete booking indicators
  if (isCompleteBookingAction) {
    leadType = 'Complete Booking';
    status = 'Converted';
    console.log(`✅ Complete booking detected via action: ${data.action}`);
  }
  // Priority 2: Check lead status indicators
  else if (isCompleteLeadStatus) {
    leadType = 'Complete Booking';
    status = 'Converted';
    console.log(`✅ Complete booking detected via lead_status: ${data.lead_status}`);
  }
  // Priority 3: Traditional logic - has booking ID and complete info
  else if (hasBookingId && hasCompleteCustomerInfo && hasAppointmentInfo) {
    leadType = 'Complete Booking';
    status = 'Converted';
    console.log(`✅ Complete booking detected via data completeness`);
  } 
  // Priority 4: Qualified lead logic
  else if (hasServiceInfo && (hasCompleteCustomerInfo || completionPercentage >= 50)) {
    leadType = 'Qualified Lead';
    status = 'In Progress';
  } 
  // Priority 5: Initial lead
  else if (hasServiceInfo) {
    leadType = 'Initial Lead';
    status = 'Started';
  } 
  // Priority 6: Anonymous visitor
  else {
    leadType = 'Anonymous Visitor';
    status = 'Browsing';
  }
  
  // CRITICAL FIX: Override completion percentage for complete bookings
  const finalCompletionPercentage = (leadType === 'Complete Booking') ? 100 : completionPercentage;
  
  console.log(`Lead analysis result: ${leadType} | ${status} | ${finalCompletionPercentage}%`);
  
  return {
    leadType: leadType,
    status: status,
    completionPercentage: finalCompletionPercentage,
    isComplete: leadType === 'Complete Booking'
  };
}

/**
 * Get field value based on header
 */
function getFieldValue(header, data, leadAnalysis, sessionId) {
  const timestamp = new Date();
  
  switch (header) {
    case 'Timestamp':
      return timestamp;
    case 'Lead Type':
      // CRITICAL FIX: Use leadAnalysis result, but fallback to data values
      return leadAnalysis.leadType || data.lead_type || 'Unknown';
    case 'Status':
      // CRITICAL FIX: Use leadAnalysis result, but fallback to data values
      return leadAnalysis.status || data.lead_status || data.status || 'New';
    case 'Booking ID':
      // CRITICAL FIX: For complete bookings, ALWAYS prioritize actual booking_id
      if (data.action === 'complete_booking') {
        // Try multiple possible field names for booking ID
        const bookingId = data.booking_id || data.id;
        if (bookingId && String(bookingId).trim() !== '' && String(bookingId) !== 'undefined') {
          console.log(`✅ Complete booking detected - using booking_id: ${bookingId}`);
          return String(bookingId);
        }
        console.log(`⚠️ Complete booking but no valid booking_id found. Available keys:`, Object.keys(data));
      }
      // For incomplete leads or when no booking_id is available, use session_id
      const fallbackId = data.booking_id || data.id || sessionId || '';
      console.log(`ℹ️ Using fallback ID: ${fallbackId} (action: ${data.action})`);
      return fallbackId;
    case 'Customer Name':
      return data.customer_name || data.name || '';
    case 'Customer Email':
      return data.customer_email || data.email || '';
    case 'Customer Phone':
      return data.customer_phone || data.phone || '';
    case 'Customer Address':
      return data.customer_address || data.address || '';
    case 'ZIP Code':
      return data.zip_code || data.roof_zip || data.windows_zip || data.bathroom_zip || 
             data.siding_zip || data.kitchen_zip || data.decks_zip || data.adu_zip || '';
    case 'City':
      return data.city || '';
    case 'State':
      return data.state || '';
    case 'Service':
      return data.service_type || data.service || data.service_name || '';
    case 'Company':
      return data.company_name || data.company || '';
    case 'Date':
      // CRITICAL FIX: Try multiple date formats that WordPress sends
      return data.formatted_date || data.booking_date || data.selected_date || data.date || '';
    case 'Time':
      // CRITICAL FIX: Try multiple time formats that WordPress sends
      return data.formatted_time || data.booking_time || data.selected_time || data.time || '';
    case 'UTM Source':
      return data.utm_source || '';
    case 'UTM Medium':
      return data.utm_medium || '';
    case 'UTM Campaign':
      return data.utm_campaign || '';
    case 'UTM Term':
      return data.utm_term || '';
    case 'UTM Content':
      return data.utm_content || '';
    case 'GCLID':
      return data.gclid || '';
    case 'Referrer':
      return data.referrer || data.http_referer || '';
    case 'Session ID':
      return sessionId || '';
    case 'Form Step':
      return determineFormStep(data);
    case 'Completion %':
      // CRITICAL FIX: Use leadAnalysis completion percentage
      return (leadAnalysis.completionPercentage || data.completion_percentage || 0) + '%';
    case 'Lead Score':
      // CRITICAL FIX: Use calculated lead score or provided score
      return calculateLeadScore(data, leadAnalysis) || data.lead_score || 0;
    case 'Conversion Time':
      // CRITICAL FIX: For complete bookings, always set conversion time
      return (leadAnalysis.isComplete || data.action === 'complete_booking') ? (data.conversion_time || timestamp) : '';
    case 'Created Date':
      return data.created_date || data.created_at || timestamp;
    case 'Last Updated':
      return timestamp;
    case 'Notes':
      // CRITICAL FIX: Combine multiple note fields
      const notes = [
        data.notes,
        data.special_notes, 
        data.message,
        data.specifications
      ].filter(note => note && note.trim() !== '').join('; ');
      return notes || '';
    case 'Specifications':
      return data.specifications || data.service_details || '';
    
    // Service-specific fields
    case 'Roof Action':
      return data.roof_action || '';
    case 'Roof Material':
      return data.roof_material || '';
    case 'Windows Action':
      return data.windows_action || '';
    case 'Windows Replace Qty':
      return data.windows_replace_qty || '';
    case 'Windows Repair Needed':
      return data.windows_repair_needed || '';
    case 'Bathroom Option':
      return data.bathroom_option || '';
    case 'Siding Option':
      return data.siding_option || '';
    case 'Siding Material':
      return data.siding_material || '';
    case 'Kitchen Action':
      return data.kitchen_action || '';
    case 'Kitchen Component':
      return data.kitchen_component || '';
    case 'Decks Action':
      return data.decks_action || '';
    case 'Decks Material':
      return data.decks_material || '';
    case 'ADU ZIP':
      return data.adu_zip || '';
    case 'ADU Action':
      return data.adu_action || '';
    case 'ADU Type':
      return data.adu_type || '';
      
    default:
      return '';
  }
}

/**
 * Determine which form step the user is on
 */
function determineFormStep(data) {
  // CRITICAL FIX: Check for complete booking first
  if (data.action === 'complete_booking') {
    return 'Step 4: Confirmation';
  }
  
  // Traditional step detection
  if ((data.booking_date || data.selected_date) && (data.booking_time || data.selected_time)) {
    return 'Step 4: Confirmation';
  }
  if (data.customer_name && data.customer_email) {
    return 'Step 3: Contact Info';
  }
  if (data.selected_date || data.booking_date) {
    return 'Step 2: Date Selection';
  }
  if (data.service_type || data.service) {
    return 'Step 1: Service Selection';
  }
  return 'Initial Visit';
}

/**
 * Calculate lead score
 */
function calculateLeadScore(data, leadAnalysis) {
  // CRITICAL FIX: For complete bookings, always return high score
  if (data.action === 'complete_booking' || leadAnalysis?.isComplete) {
    return 100;
  }
  
  let score = leadAnalysis?.completionPercentage || data.completion_percentage || 0;
  
  // Service type bonus
  const serviceScores = {
    'ADU': 95, 'Roof': 90, 'Kitchen': 85, 'Bathroom': 80,
    'Siding': 75, 'Windows': 70, 'Decks': 65
  };
  
  const service = data.service || data.service_type || '';
  if (serviceScores[service]) {
    score = Math.max(score, serviceScores[service]);
  }
  
  // UTM source bonus
  const utmSource = (data.utm_source || '').toLowerCase();
  if (utmSource.includes('google')) score += 10;
  else if (utmSource.includes('facebook')) score += 8;
  else if (utmSource) score += 5;
  
  // Contact completeness bonus
  if (data.customer_email || data.email) score += 5;
  if (data.customer_phone || data.phone) score += 5;
  if (data.customer_address || data.address) score += 5;
  
  return Math.min(100, Math.max(0, score));
}

/**
 * Log webhook activity to Webhook_Log sheet with enhanced debugging
 */
function logWebhookActivity(logData) {
  try {
    const logSheet = getOrCreateLogSheet();
    
    // Ensure we have all required data with fallbacks
    const timestamp = logData.timestamp || new Date();
    const eventType = logData.eventType || 'Unknown Event';
    const status = logData.status || 'unknown';
    const sessionId = logData.sessionId || 'no-session';
    const customerName = logData.customerName || 'Anonymous';
    const service = logData.service || 'Unknown Service';
    const dataReceived = logData.dataReceived || 'No data';
    const processingResult = logData.processingResult || 'No result';
    const errorMessage = logData.errorMessage || '';
    const responseTime = logData.responseTime || 0;
    
    // Create human-readable timestamp
    const formattedTimestamp = Utilities.formatDate(timestamp, Session.getScriptTimeZone(), "MM/dd/yyyy HH:mm:ss");
    
    const logRow = [
      formattedTimestamp,
      eventType,
      status,
      sessionId,
      customerName,
      service,
      dataReceived,
      processingResult,
      errorMessage,
      `${responseTime}ms`
    ];
    
    logSheet.appendRow(logRow);
    
    // Color-code rows based on status
    const rowNum = logSheet.getLastRow();
    const rowRange = logSheet.getRange(rowNum, 1, 1, logRow.length);
    
    if (status === 'error') {
      rowRange.setBackground('#ffebee'); // Light red for errors
    } else if (status === 'success') {
      rowRange.setBackground('#e8f5e8'); // Light green for success
    } else {
      rowRange.setBackground('#fff3e0'); // Light orange for unknown
    }
    
    // Auto-cleanup old logs
    if (logSheet.getLastRow() > CONFIG.MAX_LOG_ROWS) {
      cleanupOldLogs(logSheet);
    }
    
    // Also log to console for debugging
    console.log(`Webhook ${status}: ${eventType} - ${customerName} - ${service}`);
    
  } catch (error) {
    // Fallback logging to console if sheet logging fails
    console.error('CRITICAL: Failed to log webhook activity to sheet:', error.toString());
    console.log('Failed log data:', JSON.stringify(logData));
    
    // Try to create a basic error log entry
    try {
      const spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
      let errorSheet = spreadsheet.getSheetByName('Error_Log');
      if (!errorSheet) {
        errorSheet = spreadsheet.insertSheet('Error_Log');
        errorSheet.getRange(1, 1, 1, 3).setValues([['Timestamp', 'Error', 'Data']]);
        errorSheet.getRange(1, 1, 1, 3).setFontWeight('bold').setBackground('#ff0000').setFontColor('#ffffff');
      }
      errorSheet.appendRow([new Date(), error.toString(), JSON.stringify(logData)]);
    } catch (fallbackError) {
      console.error('CRITICAL: Complete logging failure:', fallbackError.toString());
    }
  }
}

/**
 * Get or create main booking sheet
 */
function getOrCreateMainSheet() {
  const spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
  let sheet = spreadsheet.getSheetByName(CONFIG.MAIN_SHEET_NAME);
  
  if (!sheet) {
    sheet = spreadsheet.insertSheet(CONFIG.MAIN_SHEET_NAME);
    initializeSheetHeaders(sheet, BOOKING_HEADERS);
  }
  
  return sheet;
}

/**
 * Get or create webhook log sheet with enhanced initialization
 */
function getOrCreateLogSheet() {
  const spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
  let sheet = spreadsheet.getSheetByName(CONFIG.WEBHOOK_LOG_SHEET);
  
  if (!sheet) {
    // Create new sheet
    sheet = spreadsheet.insertSheet(CONFIG.WEBHOOK_LOG_SHEET);
    console.log('Created new Webhook_Log sheet');
  }
  
  // Check if headers exist and are correct
  if (sheet.getLastRow() === 0 || !hasCorrectHeaders(sheet, WEBHOOK_LOG_HEADERS)) {
    initializeLogSheetHeaders(sheet);
    console.log('Initialized Webhook_Log headers');
  }
  
  return sheet;
}

/**
 * Check if sheet has correct headers
 */
function hasCorrectHeaders(sheet, expectedHeaders) {
  if (sheet.getLastRow() === 0) return false;
  
  try {
    const headerRow = sheet.getRange(1, 1, 1, expectedHeaders.length).getValues()[0];
    for (let i = 0; i < expectedHeaders.length; i++) {
      if (headerRow[i] !== expectedHeaders[i]) {
        return false;
      }
    }
    return true;
  } catch (error) {
    return false;
  }
}

/**
 * Initialize log sheet headers with enhanced formatting
 */
function initializeLogSheetHeaders(sheet) {
  // Clear any existing content
  sheet.clear();
  
  // Set headers
  sheet.getRange(1, 1, 1, WEBHOOK_LOG_HEADERS.length).setValues([WEBHOOK_LOG_HEADERS]);
  
  // Format header row
  const headerRange = sheet.getRange(1, 1, 1, WEBHOOK_LOG_HEADERS.length);
  headerRange.setFontWeight("bold");
  headerRange.setBackground("#1a73e8");
  headerRange.setFontColor("#ffffff");
  headerRange.setBorder(true, true, true, true, true, true);
  
  // Set column widths for better readability
  sheet.setColumnWidth(1, 150); // Timestamp
  sheet.setColumnWidth(2, 120); // Event Type
  sheet.setColumnWidth(3, 80);  // Status
  sheet.setColumnWidth(4, 150); // Session ID
  sheet.setColumnWidth(5, 150); // Customer Name
  sheet.setColumnWidth(6, 120); // Service
  sheet.setColumnWidth(7, 300); // Data Received
  sheet.setColumnWidth(8, 200); // Processing Result
  sheet.setColumnWidth(9, 200); // Error Message
  sheet.setColumnWidth(10, 100); // Response Time
  
  // Freeze header row
  sheet.setFrozenRows(1);
  
  // Add a sample row for reference (will be overwritten by real data)
  const sampleRow = [
    'Sample Timestamp',
    'Sample Event',
    'success/error',
    'session_id_here',
    'Customer Name',
    'Service Type',
    'JSON data received',
    'Processing result',
    'Error if any',
    '100ms'
  ];
  
  sheet.getRange(2, 1, 1, sampleRow.length).setValues([sampleRow]);
  sheet.getRange(2, 1, 1, sampleRow.length).setFontStyle('italic').setFontColor('#666666');
}

/**
 * Initialize sheet headers
 */
function initializeSheetHeaders(sheet, headers) {
  sheet.getRange(1, 1, 1, headers.length).setValues([headers]);
  
  const headerRange = sheet.getRange(1, 1, 1, headers.length);
  headerRange.setFontWeight("bold");
  headerRange.setBackground("#4285f4");
  headerRange.setFontColor("#ffffff");
  
  sheet.setFrozenRows(1);
  sheet.autoResizeColumns(1, headers.length);
}

/**
 * Find existing entry by session ID or booking ID
 */
function findExistingEntry(sheet, sessionId) {
  if (!sessionId) return -1;
  
  const dataRange = sheet.getDataRange();
  const values = dataRange.getValues();
  const headers = values[0];
  
  const sessionIdCol = headers.indexOf('Session ID');
  const bookingIdCol = headers.indexOf('Booking ID');
  
  // Search for existing entry
  for (let i = 1; i < values.length; i++) {
    const row = values[i];
    
    // Check session ID match
    if (sessionIdCol >= 0 && row[sessionIdCol] === sessionId) {
      return i + 1;
    }
    
    // Check booking ID match
    if (bookingIdCol >= 0 && row[bookingIdCol] === sessionId) {
      return i + 1;
    }
  }
  
  return -1;
}

/**
 * Generate session ID if none provided
 */
function generateSessionId() {
  return 'session_' + new Date().getTime() + '_' + Math.random().toString(36).substr(2, 5);
}

/**
 * Clean up old logs to keep sheet manageable
 */
function cleanupOldLogs(sheet) {
  const currentRows = sheet.getLastRow();
  const rowsToDelete = currentRows - CONFIG.MAX_LOG_ROWS + 50; // Keep some buffer
  
  if (rowsToDelete > 0) {
    sheet.deleteRows(2, rowsToDelete); // Skip header row
  }
}

/**
 * Get webhook statistics from log sheet for monitoring
 */
function getWebhookStats() {
  try {
    const logSheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(CONFIG.WEBHOOK_LOG_SHEET);
    if (!logSheet) return { error: 'Log sheet not found' };
    
    const data = logSheet.getDataRange().getValues();
    if (data.length <= 1) return { total: 0, success: 0, errors: 0 };
    
    const rows = data.slice(1); // Skip header
    const total = rows.length;
    const success = rows.filter(row => row[2] === 'success').length;
    const errors = rows.filter(row => row[2] === 'error').length;
    
    // Get recent activity (last 24 hours)
    const yesterday = new Date(Date.now() - 24 * 60 * 60 * 1000);
    const recent = rows.filter(row => {
      const timestamp = new Date(row[0]);
      return timestamp > yesterday;
    }).length;
    
    return {
      total: total,
      success: success,
      errors: errors,
      successRate: total > 0 ? Math.round((success / total) * 100) : 0,
      recentActivity: recent
    };
    
  } catch (error) {
    return { error: error.toString() };
  }
}

/**
 * Maintenance function - clean up old data
 */
function performMaintenance() {
  const results = {
    timestamp: new Date(),
    mainSheetRows: 0,
    logSheetRows: 0,
    cleanupPerformed: false
  };
  
  try {
    // Check main sheet
    const mainSheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(CONFIG.MAIN_SHEET_NAME);
    if (mainSheet) {
      results.mainSheetRows = mainSheet.getLastRow();
    }
    
    // Check and clean log sheet
    const logSheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(CONFIG.WEBHOOK_LOG_SHEET);
    if (logSheet) {
      results.logSheetRows = logSheet.getLastRow();
      
      if (results.logSheetRows > CONFIG.MAX_LOG_ROWS) {
        cleanupOldLogs(logSheet);
        results.cleanupPerformed = true;
      }
    }
    
    console.log('Maintenance completed:', results);
    
  } catch (error) {
    results.error = error.toString();
    console.error('Maintenance failed:', error);
  }
  
  return results;
}