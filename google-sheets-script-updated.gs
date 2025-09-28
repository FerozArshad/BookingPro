/**
 * Optimized BookingPro Google Sheets Integration
 * Enhanced with robust parsing, header mapping, and data integrity
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
 * Expected headers - Complete list (will auto-append missing ones)
 */
const BOOKING_HEADERS = [
  'Timestamp', 'Lead Type', 'Status', 'Booking ID', 'Customer Name', 'Customer Email', 'Customer Phone',
  'Customer Address', 'ZIP Code', 'City', 'State', 'Service', 'Company', 'Date', 'Time',
  'UTM Source', 'UTM Medium', 'UTM Campaign', 'UTM Term', 'UTM Content', 'GCLID', 'Referrer',
  'Roof Action', 'Roof Material', 'Windows Action', 'Windows Replace Qty', 'Windows Repair Needed',
  'Bathroom Option', 'Siding Option', 'Siding Material', 'Kitchen Action', 'Kitchen Component',
  'Decks Action', 'Decks Material', 'ADU Action', 'ADU Type', 'Specifications',
  'Session ID', 'Form Step', 'Completion %', 'Lead Score', 'Conversion Time', 'Created Date', 'Last Updated', 'Notes'
];

const WEBHOOK_LOG_HEADERS = [
  'Timestamp', 'Event Type', 'Status', 'Session ID', 'Customer Name', 'Service',
  'Data Received', 'Processing Result', 'Error Message', 'Response Time'
];

/**
 * Helper: Get first value from CSV string
 */
function firstCSV(value) {
  if (!value) return '';
  const str = String(value).trim();
  const firstComma = str.indexOf(',');
  return firstComma > 0 ? str.substring(0, firstComma).trim() : str;
}

/**
 * Helper: Split CSV string into array of trimmed values
 */
function splitCSV(value) {
  if (!value) return [];
  return String(value).split(',').map(v => v.trim()).filter(v => v.length > 0);
}

/**
 * Helper: Collect CSV values from multiple data keys
 */
function collectCSVFromKeys(data, keys) {
  const values = [];
  keys.forEach(key => {
    const value = data[key];
    if (value) {
      splitCSV(value).forEach(v => {
        if (v && !values.includes(v)) {
          values.push(v);
        }
      });
    }
  });
  return values;
}

/**
 * Helper: Convert time to AM/PM format
 */
function toAmPm(timeStr) {
  if (!timeStr) return '';
  
  const time = timeStr.trim();
  
  // Already in AM/PM format
  if (time.match(/\d+:\d+\s*(AM|PM)/i)) {
    return time;
  }
  
  // Handle HH:mm format
  const match = time.match(/^(\d{1,2}):(\d{2})$/);
  if (match) {
    let hours = parseInt(match[1]);
    const minutes = match[2];
    const ampm = hours >= 12 ? 'PM' : 'AM';
    
    if (hours === 0) hours = 12;
    else if (hours > 12) hours -= 12;
    
    return `${hours}:${minutes} ${ampm}`;
  }
  
  // Return as-is if format not recognized
  return time;
}

/**
 * Helper: Pad array to target length or repeat single value
 */
function padOrRepeat(arr, targetLength) {
  if (arr.length === 0) return new Array(targetLength).fill('');
  if (arr.length === 1) return new Array(targetLength).fill(arr[0]);
  if (arr.length >= targetLength) return arr.slice(0, targetLength);
  return arr.concat(new Array(targetLength - arr.length).fill(''));
}

/**
 * Helper: Extract multiple companies from payload
 */
function extractMultipleCompanies(data) {
  const companies = [];
  
  console.log('🏢 COMPANY EXTRACTION DEBUG:', JSON.stringify(data));
  
  if (data.company_name) {
    console.log(`Found company_name: "${data.company_name}"`);
    splitCSV(data.company_name).forEach(company => {
      if (company && !companies.includes(company)) {
        companies.push(company);
        console.log(`Added company: "${company}"`);
      }
    });
  }
  
  if (data.company && data.company !== data.company_name) {
    console.log(`Found company: "${data.company}"`);
    splitCSV(data.company).forEach(company => {
      if (company && !companies.includes(company)) {
        companies.push(company);
        console.log(`Added company: "${company}"`);
      }
    });
  }
  
  for (let i = 1; i <= 5; i++) {
    const companyField = data[`company${i}`] || data[`company_${i}`] || data[`company_name_${i}`];
    if (companyField) {
      console.log(`Found company${i}: "${companyField}"`);
      splitCSV(companyField).forEach(company => {
        if (company && !companies.includes(company)) {
          companies.push(company);
          console.log(`Added company: "${company}"`);
        }
      });
    }
  }
  
  if (data.companies && Array.isArray(data.companies)) {
    console.log('Found companies array:', data.companies);
    data.companies.forEach(comp => {
      const name = comp.name || comp.company_name || comp;
      if (name) {
        splitCSV(name).forEach(company => {
          if (company && !companies.includes(company)) {
            companies.push(company);
            console.log(`Added company from array: "${company}"`);
          }
        });
      }
    });
  }
  
  if (data.appointments && Array.isArray(data.appointments)) {
    console.log('Found appointments array:', data.appointments);
    data.appointments.forEach(apt => {
      const companyValue = apt.company || apt.company_name;
      if (companyValue) {
        splitCSV(companyValue).forEach(company => {
          if (company && !companies.includes(company)) {
            companies.push(company);
            console.log(`Added company from appointments: "${company}"`);
          }
        });
      }
    });
  }
  
  console.log('🏢 Final extracted companies:', companies);
  return companies;
}

/**
 * Helper: Extract multiple dates from payload
 */
function extractMultipleDates(data) {
  const dates = [];
  
  console.log('📅 DATE EXTRACTION DEBUG:', JSON.stringify(data));
  
  const dateKeys = [
    'selected_date', 'booking_date', 'date',
    'date1', 'date2', 'date3', 'date4', 'date5',
    'date_1', 'date_2', 'date_3', 'date_4', 'date_5',
    'booking_date_1', 'booking_date_2', 'booking_date_3', 'booking_date_4', 'booking_date_5',
    'selected_date_1', 'selected_date_2', 'selected_date_3', 'selected_date_4', 'selected_date_5'
  ];
  
  dateKeys.forEach(key => {
    const value = data[key];
    if (value) {
      console.log(`Found date key "${key}": "${value}"`);
      splitCSV(value).forEach(date => {
        if (date && !dates.includes(date)) {
          dates.push(date);
          console.log(`Added date: "${date}"`);
        }
      });
    }
  });
  
  if (data.dates && Array.isArray(data.dates)) {
    console.log('Found dates array:', data.dates);
    data.dates.forEach(dateItem => {
      const dateValue = dateItem.date || dateItem.booking_date || dateItem;
      if (dateValue) {
        splitCSV(dateValue).forEach(date => {
          if (date && !dates.includes(date)) {
            dates.push(date);
            console.log(`Added date from array: "${date}"`);
          }
        });
      }
    });
  }
  
  if (data.appointments && Array.isArray(data.appointments)) {
    console.log('Found appointments array:', data.appointments);
    data.appointments.forEach(apt => {
      const dateValue = apt.date || apt.selected_date || apt.booking_date;
      if (dateValue) {
        splitCSV(dateValue).forEach(date => {
          if (date && !dates.includes(date)) {
            dates.push(date);
            console.log(`Added date from appointments: "${date}"`);
          }
        });
      }
    });
  }
  
  console.log('📅 Final extracted dates:', dates);
  return dates;
}

/**
 * Helper: Extract multiple times from payload
 */
function extractMultipleTimes(data) {
  const times = [];
  
  const timeKeys = [
    'selected_time', 'booking_time', 'time',
    'time1', 'time2', 'time3', 'time4', 'time5',
    'time_1', 'time_2', 'time_3', 'time_4', 'time_5',
    'booking_time_1', 'booking_time_2', 'booking_time_3', 'booking_time_4', 'booking_time_5',
    'selected_time_1', 'selected_time_2', 'selected_time_3', 'selected_time_4', 'selected_time_5'
  ];
  
  console.log('🕐 TIME EXTRACTION DEBUG:', JSON.stringify(data));
  
  timeKeys.forEach(key => {
    const value = data[key];
    if (value) {
      console.log(`Found time key "${key}": "${value}"`);
      splitCSV(value).forEach(time => {
        if (time && !times.includes(time)) {
          times.push(time);
          console.log(`Added time: "${time}"`);
        }
      });
    }
  });
  
  if (data.times && Array.isArray(data.times)) {
    console.log('Found times array:', data.times);
    data.times.forEach(timeItem => {
      const timeValue = timeItem.time || timeItem.booking_time || timeItem;
      if (timeValue) {
        splitCSV(timeValue).forEach(time => {
          if (time && !times.includes(time)) {
            times.push(time);
            console.log(`Added time from array: "${time}"`);
          }
        });
      }
    });
  }
  
  if (data.appointments && Array.isArray(data.appointments)) {
    console.log('Found appointments array:', data.appointments);
    data.appointments.forEach(apt => {
      const timeValue = apt.time || apt.selected_time || apt.booking_time;
      if (timeValue) {
        splitCSV(timeValue).forEach(time => {
          if (time && !times.includes(time)) {
            times.push(time);
            console.log(`Added time from appointments: "${time}"`);
          }
        });
      }
    });
  }
  
  console.log('🕐 Final extracted times:', times);
  return times;
}

/**
 * Helper: Build aligned company, date, and time data
 */
function buildCompanyDateTime(data) {
  const companies = extractMultipleCompanies(data);
  const dates = extractMultipleDates(data);
  const times = extractMultipleTimes(data);
  
  console.log(`Extracted data - Companies: [${companies.join(', ')}], Dates: [${dates.join(', ')}], Times: [${times.join(', ')}]`);
  
  const maxLength = Math.max(companies.length, dates.length, times.length);
  const n = Math.min(3, Math.max(1, maxLength));
  
  const alignedCompanies = padOrRepeat(companies, n);
  const alignedDates = padOrRepeat(dates, n);
  const alignedTimes = padOrRepeat(times, n);
  
  console.log(`Aligned data (n=${n}) - Companies: [${alignedCompanies.join(', ')}], Dates: [${alignedDates.join(', ')}], Times: [${alignedTimes.join(', ')}]`);
  
  return {
    companiesStr: alignedCompanies.join('; '),
    datesStr: alignedDates.join('; '),
    timesStr: alignedTimes.join('; ')
  };
}

/**
 * Helper: Flatten nested objects to single-level key map
 */
function flattenObject(obj, prefix = '', result = {}) {
  for (const key in obj) {
    if (obj.hasOwnProperty(key)) {
      const value = obj[key];
      const newKey = prefix ? `${prefix}_${key}` : key;
      
      if (value !== null && typeof value === 'object' && !Array.isArray(value)) {
        flattenObject(value, newKey, result);
      } else {
        result[newKey] = value;
      }
    }
  }
  return result;
}

/**
 * Enhanced robust parsing with multiple content-type support
 */
function parseIncomingData(e) {
  let data = {};
  let parseSuccess = false;
  let contentType = '';
  let parseMethod = 'unknown';
  
  try {
    // Check for POST data first
    if (e.postData && e.postData.contents) {
      contentType = e.postData.type || '';
      const contents = e.postData.contents;
      
      console.log(`Parsing content-type: ${contentType}`);
      
      // 1. JSON parsing (application/json)
      if (contentType.includes('application/json')) {
        try {
          data = JSON.parse(contents);
          parseSuccess = true;
          parseMethod = 'json';
          console.log('Successfully parsed JSON data');
        } catch (jsonError) {
          console.log('JSON parse failed:', jsonError.toString());
        }
      }
      
      // 2. URL-encoded parsing (application/x-www-form-urlencoded)
      if (!parseSuccess && contentType.includes('application/x-www-form-urlencoded')) {
        try {
          data = parseUrlEncodedSafe(contents);
          parseSuccess = true;
          parseMethod = 'urlencoded';
          console.log('Successfully parsed URL-encoded data');
        } catch (urlError) {
          console.log('URL-encoded parse failed:', urlError.toString());
        }
      }
      
      // 3. Multipart form data (use e.parameters)
      if (!parseSuccess && contentType.includes('multipart/form-data')) {
        try {
          data = e.parameters || {};
          parseSuccess = true;
          parseMethod = 'multipart';
          console.log('Successfully parsed multipart data');
        } catch (multipartError) {
          console.log('Multipart parse failed:', multipartError.toString());
        }
      }
      
      // 4. Fallback: try JSON first, then URL-encoded
      if (!parseSuccess) {
        // Try JSON fallback
        try {
          data = JSON.parse(contents);
          parseSuccess = true;
          parseMethod = 'json_fallback';
          console.log('Fallback JSON parsing successful');
        } catch (jsonFallbackError) {
          // Try URL-encoded fallback
          try {
            data = parseUrlEncodedSafe(contents);
            parseSuccess = true;
            parseMethod = 'urlencoded_fallback';
            console.log('Fallback URL-encoded parsing successful');
          } catch (urlFallbackError) {
            console.log('All parsing methods failed');
          }
        }
      }
    }
    
    // 5. Parameter fallback
    if (!parseSuccess && (e.parameters || e.parameter)) {
      data = e.parameters || e.parameter || {};
      parseSuccess = true;
      parseMethod = 'parameters';
      console.log('Using parameter data');
    }
    
    // 6. Final fallback
    if (!parseSuccess) {
      data = { 
        error: 'No data received in webhook',
        raw_data: e.postData ? e.postData.contents : 'No post data',
        content_type: contentType
      };
      parseMethod = 'error_fallback';
      console.log('No data found in webhook, using error fallback');
    }
    
    // Flatten nested objects
    data = flattenObject(data);
    
  } catch (error) {
    console.error('Critical parsing error:', error);
    data = { 
      parse_error: error.toString(),
      raw_data: e.postData ? e.postData.contents : 'No post data'
    };
    parseMethod = 'critical_error';
  }
  
  return {
    data: data,
    success: parseSuccess,
    method: parseMethod,
    contentType: contentType
  };
}

/**
 * Safe URL-encoded parser using indexOf to preserve values with '='
 */
function parseUrlEncodedSafe(dataString) {
  const params = {};
  const pairs = dataString.split('&');
  
  for (let i = 0; i < pairs.length; i++) {
    const pair = pairs[i];
    const equalIndex = pair.indexOf('=');
    
    if (equalIndex > 0) {
      const key = decodeURIComponent(pair.substring(0, equalIndex));
      const value = decodeURIComponent(pair.substring(equalIndex + 1).replace(/\+/g, ' '));
      params[key] = value;
    }
  }
  
  return params;
}

/**
 * Build header mapping with performance caching
 */
function buildHeaderColumnMap(sheet) {
  const cacheKey = 'headerMap_' + sheet.getSheetName();
  let headerMap = PropertiesService.getScriptProperties().getProperty(cacheKey);
  
  if (headerMap) {
    try {
      headerMap = JSON.parse(headerMap);
      const currentLastCol = sheet.getLastColumn();
      if (Object.keys(headerMap).length === currentLastCol) {
        return headerMap;
      }
    } catch (e) {
      console.log('Cache invalid, rebuilding header map');
    }
  }
  
  headerMap = {};
  let needsUpdate = false;
  
  let existingHeaders = [];
  if (sheet.getLastRow() >= 1) {
    const headerRange = sheet.getRange(1, 1, 1, sheet.getLastColumn());
    existingHeaders = headerRange.getValues()[0];
  }
  
  for (let i = 0; i < existingHeaders.length; i++) {
    if (existingHeaders[i]) {
      headerMap[existingHeaders[i]] = i + 1;
    }
  }
  
  const missingHeaders = [];
  for (const expectedHeader of BOOKING_HEADERS) {
    if (!headerMap[expectedHeader]) {
      missingHeaders.push(expectedHeader);
      needsUpdate = true;
    }
  }
  
  if (needsUpdate && missingHeaders.length > 0) {
    const startCol = existingHeaders.length + 1;
    const headerRange = sheet.getRange(1, startCol, 1, missingHeaders.length);
    headerRange.setValues([missingHeaders]);
    
    for (let i = 0; i < missingHeaders.length; i++) {
      headerMap[missingHeaders[i]] = startCol + i;
    }
    
    headerRange.setFontWeight("bold");
    headerRange.setBackground("#4285f4");
    headerRange.setFontColor("#ffffff");
    
    console.log(`Auto-appended missing headers: ${missingHeaders.join(', ')}`);
  }
  
  try {
    PropertiesService.getScriptProperties().setProperty(cacheKey, JSON.stringify(headerMap));
  } catch (e) {
    console.log('Failed to cache header map:', e.toString());
  }
  
  return headerMap;
}

/**
 * Enhanced merge with improved update rules
 */
function mergeRowDataByHeaders(sheet, rowNumber, newDataObj, headerMap) {
  const numCols = Math.max(sheet.getLastColumn(), Object.keys(headerMap).length);
  const existingRow = sheet.getRange(rowNumber, 1, 1, numCols).getValues()[0];
  
  // Build update object
  const updates = {};
  let hasUpdates = false;
  
  for (const header in newDataObj) {
    const colIndex = headerMap[header];
    if (!colIndex) continue; // Skip unmapped headers
    
    const newValue = newDataObj[header];
    const existingValue = existingRow[colIndex - 1]; // Convert to 0-indexed
    
    // Enhanced update logic
    let shouldUpdate = false;
    
    // Always update these fields
    if (['Timestamp', 'Last Updated'].includes(header)) {
      shouldUpdate = true;
    }
    // Lead Score: only increase
    else if (header === 'Lead Score') {
      const newScore = parseInt(newValue) || 0;
      const existingScore = parseInt(existingValue) || 0;
      shouldUpdate = newScore > existingScore;
    }
    // Customer info: always update
    else if (['Customer Name', 'Customer Email', 'Customer Phone'].includes(header)) {
      shouldUpdate = newValue !== undefined && newValue !== null;
    }
    // Fill empty cells
    else if (!existingValue || existingValue.toString().trim() === '') {
      shouldUpdate = newValue !== undefined && newValue !== null;
    }
    // Update if new value is different and not empty
    else if (newValue !== undefined && newValue !== null && newValue !== existingValue) {
      shouldUpdate = true;
    }
    
    if (shouldUpdate) {
      updates[colIndex] = newValue;
      hasUpdates = true;
    }
  }
  
  // Apply updates with batch write for better performance
  if (hasUpdates) {
    // Build batch update array
    const updateEntries = Object.entries(updates).map(([colIndex, value]) => {
      return {
        range: sheet.getRange(rowNumber, parseInt(colIndex)),
        value: value
      };
    });
    
    // Apply all updates in a single batch operation
    updateEntries.forEach(entry => {
      entry.range.setValue(entry.value);
    });
  }
  
  return hasUpdates;
}

/**
 * Enhanced row finding with proper ID matching
 */
function findExistingEntryEnhanced(sheet, sessionId, bookingId) {
  if (!sessionId && !bookingId) return -1;
  
  const dataRange = sheet.getDataRange();
  if (dataRange.getNumRows() <= 1) return -1;
  
  const values = dataRange.getValues();
  const headers = values[0];
  
  const sessionIdCol = headers.indexOf('Session ID');
  const bookingIdCol = headers.indexOf('Booking ID');
  
  // Search for existing entry
  for (let i = 1; i < values.length; i++) {
    const row = values[i];
    
    // Check booking ID match first (higher priority)
    if (bookingId && bookingIdCol >= 0 && row[bookingIdCol] === bookingId) {
      console.log(`Found existing row by Booking ID: ${bookingId} at row ${i + 1}`);
      return i + 1;
    }
    
    // Check session ID match
    if (sessionId && sessionIdCol >= 0 && row[sessionIdCol] === sessionId) {
      console.log(`Found existing row by Session ID: ${sessionId} at row ${i + 1}`);
      return i + 1;
    }
  }
  
  return -1;
}

/**
 * Main webhook entry point with enhanced parsing
 */
function doPost(e) {
  const startTime = Date.now();
  let data = {}; // Declare data outside try block to fix scope issue
  
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
    // Enhanced parsing
    const parseResult = parseIncomingData(e);
    data = parseResult.data; // Assign to outer scope variable
    const parseSuccess = parseResult.success;
    
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
      JSON.stringify(data).substring(0, 500), // Truncated for logging only
      parseSuccess ? `Parsed via ${parseResult.method}: session=${sessionForLog}, customer=${customerForLog}, service=${serviceForLog}` : 'Parse failed, using fallback',
      parseSuccess ? '' : `Parse error: ${parseResult.method}`,
      `${Date.now() - startTime}ms`
    ];
    
    try {
      const logSheet = getOrCreateLogSheet();
      logSheet.appendRow(dataLog);
    } catch (logError) {
      console.error('Failed to log data parsing:', logError);
    }
    
    // Process the webhook data
    const result = processWebhookDataEnhanced(data, true);
    
    // Log successful processing with performance metrics
    const responseTime = Date.now() - startTime;
    const performanceStatus = responseTime > 1000 ? 'slow' : (responseTime > 500 ? 'moderate' : 'fast');
    
    logWebhookActivity({
      timestamp: new Date(),
      eventType: result.isComplete ? 'Complete Booking' : 'Incomplete Lead',
      status: 'success',
      sessionId: result.sessionId || 'unknown',
      customerName: data.customer_name || data.name || 'Anonymous',
      service: data.service || data.service_type || 'Unknown',
      dataReceived: JSON.stringify(data).substring(0, 500),
      processingResult: `${JSON.stringify(result).substring(0, 150)} | Performance: ${performanceStatus}`,
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
 * Enhanced webhook data processing
 */
function processWebhookDataEnhanced(data, logActivity = false) {
  if (!data || typeof data !== 'object') {
    throw new Error('Invalid data received');
  }
  
  // CRITICAL SESSION MANAGEMENT: Check for session termination
  if (data.session_completed === true || data.session_terminated === true) {
    console.log(`🚫 Session ${data.session_id} is terminated - blocking further incomplete_lead processing`);
    
    // Only process if this is a complete_booking action
    if (data.action !== 'complete_booking') {
      return {
        sessionId: data.session_id,
        action: 'blocked_terminated_session',
        message: 'Session terminated - incomplete webhooks blocked',
        isComplete: false
      };
    }
  }
  
  // Extract session ID and booking ID
  let sessionId = data.session_id;
  if (!sessionId || sessionId === 'no-session' || sessionId === 'unknown') {
    sessionId = generateSessionId();
  }
  
  const bookingId = data.booking_id || data.id;
  
  // Analyze lead completion
  const leadAnalysis = analyzeLead(data);
  
  // Get or create main sheet with caching
  const mainSheet = getOrCreateMainSheet();
  
  // Build header mapping with caching for better performance
  const headerMap = buildHeaderColumnMap(mainSheet);
  
  // Check for existing entry
  const existingRow = findExistingEntryEnhanced(mainSheet, sessionId, bookingId);
  
  // Build data object using header names
  const dataObj = buildDataObject(data, leadAnalysis, sessionId);
  
  let rowNumber;
  let action;
  
  if (existingRow > 0) {
    // Update existing row using header mapping
    const hasUpdates = mergeRowDataByHeaders(mainSheet, existingRow, dataObj, headerMap);
    rowNumber = existingRow;
    action = hasUpdates ? 'updated' : 'no_changes';
    
    if (hasUpdates) {
      // Highlight updated row
      const numCols = Math.max(mainSheet.getLastColumn(), Object.keys(headerMap).length);
      mainSheet.getRange(rowNumber, 1, 1, numCols).setBackground("#fff2cc");
    }
  } else {
    // Add new row using header mapping with batch write
    rowNumber = mainSheet.getLastRow() + 1;
    
    // Build row data array for batch write
    const maxCol = Math.max(...Object.values(headerMap));
    const rowDataArray = new Array(maxCol).fill('');
    
    // Fill row data using header mapping
    for (const header in dataObj) {
      const colIndex = headerMap[header];
      if (colIndex && colIndex <= maxCol) {
        rowDataArray[colIndex - 1] = dataObj[header]; // Convert to 0-indexed
      }
    }
    
    // Single batch write instead of multiple setValue calls
    mainSheet.getRange(rowNumber, 1, 1, maxCol).setValues([rowDataArray]);
    
    action = 'created';
    
    // Highlight new row based on completion
    const bgColor = leadAnalysis.isComplete ? "#d9ead3" : "#fce5cd";
    const numCols = Math.max(mainSheet.getLastColumn(), Object.keys(headerMap).length);
    mainSheet.getRange(rowNumber, 1, 1, numCols).setBackground(bgColor);
  }
  
  return {
    sessionId: sessionId,
    bookingId: bookingId,
    rowNumber: rowNumber,
    action: action,
    leadType: leadAnalysis.leadType,
    status: leadAnalysis.status,
    completionPercentage: leadAnalysis.completionPercentage,
    isComplete: leadAnalysis.isComplete
  };
}

/**
 * Build data object with enhanced field mapping
 */
function buildDataObject(data, leadAnalysis, sessionId) {
  const timestamp = new Date();
  
  // Get aligned company, date, and time data
  const companyDateTime = buildCompanyDateTime(data);
  
  return {
    'Timestamp': timestamp,
    'Lead Type': leadAnalysis.leadType || data.lead_type || 'Unknown',
    'Status': leadAnalysis.status || data.lead_status || data.status || 'New',
    'Booking ID': getBookingIdValue(data, sessionId),
    'Customer Name': data.customer_name || data.name || '',
    'Customer Email': data.customer_email || data.email || '',
    'Customer Phone': data.customer_phone || data.phone || '',
    'Customer Address': data.customer_address || data.address || '',
    'ZIP Code': data.zip_code || data.roof_zip || data.windows_zip || data.bathroom_zip || 
               data.siding_zip || data.kitchen_zip || data.decks_zip || data.adu_zip || '',
    'City': data.city || '',
    'State': data.state || '',
    'Service': data.service_type || data.service || data.service_name || '',
    'Company': companyDateTime.companiesStr,
    'Date': companyDateTime.datesStr,
    'Time': companyDateTime.timesStr,
    'UTM Source': data.utm_source || '',
    'UTM Medium': data.utm_medium || '',
    'UTM Campaign': data.utm_campaign || '',
    'UTM Term': data.utm_term || '',
    'UTM Content': data.utm_content || '',
    'GCLID': data.gclid || '',
    'Referrer': data.referrer || data.referer || data.http_referer || data.HTTP_REFERER || '',
    'Session ID': sessionId || '',
    'Form Step': determineFormStep(data),
    'Completion %': leadAnalysis.completionPercentage || data.completion_percentage || 0, // Store as number
    'Lead Score': calculateLeadScore(data, leadAnalysis) || data.lead_score || 0,
    'Conversion Time': (leadAnalysis.isComplete || data.action === 'complete_booking') ? (data.conversion_time || timestamp) : '',
    'Created Date': data.created_date || data.created_at || timestamp,
    'Last Updated': timestamp,
    'Notes': buildNotesField(data),
    'Specifications': data.specifications || data.service_details || '',
    
    // Service-specific fields
    'Roof Action': data.roof_action || '',
    'Roof Material': data.roof_material || '',
    'Windows Action': data.windows_action || '',
    'Windows Replace Qty': data.windows_replace_qty || '',
    'Windows Repair Needed': data.windows_repair_needed || '',
    'Bathroom Option': data.bathroom_option || '',
    'Siding Option': data.siding_option || '',
    'Siding Material': data.siding_material || '',
    'Kitchen Action': data.kitchen_action || '',
    'Kitchen Component': data.kitchen_component || '',
    'Decks Action': data.decks_action || '',
    'Decks Material': data.decks_material || '',
    'ADU Action': data.adu_action || '',
    'ADU Type': data.adu_type || ''
  };
}

/**
 * Enhanced booking ID logic
 */
function getBookingIdValue(data, sessionId) {
  // For complete bookings, prioritize actual booking_id
  if (data.action === 'complete_booking') {
    const bookingId = data.booking_id || data.id;
    if (bookingId && String(bookingId).trim() !== '' && String(bookingId) !== 'undefined') {
      console.log(`✅ Complete booking detected - using booking_id: ${bookingId}`);
      return String(bookingId);
    }
    console.log(`⚠️ Complete booking but no valid booking_id found. Available keys:`, Object.keys(data));
  }
  
  // Fallback logic
  const fallbackId = data.booking_id || data.id || sessionId || '';
  console.log(`ℹ️ Using fallback ID: ${fallbackId} (action: ${data.action})`);
  return fallbackId;
}

/**
 * Enhanced notes field builder
 */
function buildNotesField(data) {
  const notes = [
    data.notes,
    data.special_notes, 
    data.message,
    data.specifications
  ].filter(note => note && note.toString().trim() !== '').join('; ');
  return notes || '';
}

/**
 * Analyze lead completion and status
 */
function analyzeLead(data) {
  const hasBookingId = !!(data.booking_id || data.id);
  const hasCompleteCustomerInfo = !!(data.customer_name && data.customer_email && data.customer_phone);
  const hasAppointmentInfo = !!(data.booking_date || data.selected_date || data.date) && (data.booking_time || data.selected_time || data.time);
  const hasServiceInfo = !!(data.service_type || data.service);
  
  // Check if this is explicitly marked as a complete booking
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
  
  // Determine lead type and status with priority logic
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
  
  // Override completion percentage for complete bookings
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
 * Determine which form step the user is on
 */
function determineFormStep(data) {
  // Check for complete booking first
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
  // For complete bookings, always return high score
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
 * Initialize sheet headers with error handling
 */
function initializeSheetHeaders(sheet, headers) {
  if (!sheet) {
    throw new Error('Sheet parameter is null or undefined');
  }
  if (!headers || !Array.isArray(headers) || headers.length === 0) {
    throw new Error('Headers parameter must be a non-empty array');
  }
  
  try {
    // Set headers
    sheet.getRange(1, 1, 1, headers.length).setValues([headers]);
    
    // Format header row
    const headerRange = sheet.getRange(1, 1, 1, headers.length);
    headerRange.setFontWeight("bold");
    headerRange.setBackground("#4285f4");
    headerRange.setFontColor("#ffffff");
    
    // Freeze header row and auto-resize columns
    sheet.setFrozenRows(1);
    sheet.autoResizeColumns(1, headers.length);
    
    console.log(`Successfully initialized sheet headers: ${headers.length} columns`);
  } catch (error) {
    console.error('Failed to initialize sheet headers:', error.toString());
    throw new Error(`Sheet header initialization failed: ${error.toString()}`);
  }
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
 * Auto-setup function - Call this manually to initialize sheets
 */
function setupSheets() {
  const results = {
    timestamp: new Date(),
    mainSheetCreated: false,
    logSheetCreated: false,
    headersInitialized: false,
    errors: []
  };
  
  try {
    console.log('Starting sheet setup...');
    
    // Initialize main booking sheet
    try {
      const mainSheet = getOrCreateMainSheet();
      results.mainSheetCreated = true;
      console.log(`Main sheet ready: ${CONFIG.MAIN_SHEET_NAME}`);
    } catch (mainSheetError) {
      results.errors.push(`Main sheet error: ${mainSheetError.toString()}`);
    }
    
    // Initialize log sheet
    try {
      const logSheet = getOrCreateLogSheet();
      results.logSheetCreated = true;
      console.log(`Log sheet ready: ${CONFIG.WEBHOOK_LOG_SHEET}`);
    } catch (logSheetError) {
      results.errors.push(`Log sheet error: ${logSheetError.toString()}`);
    }
    
    results.headersInitialized = results.mainSheetCreated && results.logSheetCreated;
    
    if (results.errors.length === 0) {
      console.log('✅ Sheet setup completed successfully!');
    } else {
      console.log('⚠️ Sheet setup completed with errors:', results.errors);
    }
    
  } catch (error) {
    results.errors.push(`Setup error: ${error.toString()}`);
    console.error('Sheet setup failed:', error);
  }
  
  return results;
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