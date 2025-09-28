/**
 * Complete Optimized BookingPro Google Sheets Integration
 * All original functionality preserved in streamlined format
 */

const CONFIG = {
  MAIN_SHEET_NAME: 'Bookings Website',
  WEBHOOK_LOG_SHEET: 'Webhook_Log', 
  MAX_LOG_ROWS: 1000,
  TIMEOUT_MS: 30000
};

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

// Helper utilities
function firstCSV(value) {
  if (!value) return '';
  const str = String(value).trim();
  const firstComma = str.indexOf(',');
  return firstComma > 0 ? str.substring(0, firstComma).trim() : str;
}

function splitCSV(value) {
  if (!value) return [];
  return String(value).split(',').map(v => v.trim()).filter(v => v.length > 0);
}

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

function padOrRepeat(arr, targetLength) {
  if (arr.length === 0) return new Array(targetLength).fill('');
  if (arr.length === 1) return new Array(targetLength).fill(arr[0]);
  if (arr.length >= targetLength) return arr.slice(0, targetLength);
  return arr.concat(new Array(targetLength - arr.length).fill(''));
}

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

function toAmPm(timeStr) {
  if (!timeStr) return '';
  const time = timeStr.trim();
  if (time.match(/\d+:\d+\s*(AM|PM)/i)) return time;
  const match = time.match(/^(\d{1,2}):(\d{2})$/);
  if (match) {
    let hours = parseInt(match[1]);
    const minutes = match[2];
    const ampm = hours >= 12 ? 'PM' : 'AM';
    if (hours === 0) hours = 12;
    else if (hours > 12) hours -= 12;
    return `${hours}:${minutes} ${ampm}`;
  }
  return time;
}

// Enhanced parsing with all content types
function parseIncomingData(e) {
  let data = {}, parseSuccess = false, contentType = '', parseMethod = 'unknown';
  
  try {
    if (e.postData?.contents) {
      contentType = e.postData.type || '';
      const contents = e.postData.contents;
      
      // JSON parsing
      if (contentType.includes('application/json')) {
        try {
          data = JSON.parse(contents);
          parseSuccess = true;
          parseMethod = 'json';
        } catch (e) {}
      }
      
      // URL-encoded parsing
      if (!parseSuccess && contentType.includes('application/x-www-form-urlencoded')) {
        try {
          data = parseUrlEncodedSafe(contents);
          parseSuccess = true;
          parseMethod = 'urlencoded';
        } catch (e) {}
      }
      
      // Multipart form data
      if (!parseSuccess && contentType.includes('multipart/form-data')) {
        data = e.parameters || {};
        parseSuccess = true;
        parseMethod = 'multipart';
      }
      
      // Fallback attempts
      if (!parseSuccess) {
        try {
          data = JSON.parse(contents);
          parseSuccess = true;
          parseMethod = 'json_fallback';
        } catch {
          try {
            data = parseUrlEncodedSafe(contents);
            parseSuccess = true;
            parseMethod = 'urlencoded_fallback';
          } catch {}
        }
      }
    }
    
    if (!parseSuccess) {
      data = e.parameters || e.parameter || {
        error: 'No data received',
        raw_data: e.postData?.contents || 'No post data',
        content_type: contentType
      };
      parseMethod = parseSuccess ? 'parameters' : 'error_fallback';
    }
    
    // Flatten nested objects
    data = flattenObject(data);
    
  } catch (error) {
    data = { parse_error: error.toString(), raw_data: e.postData?.contents || 'No post data' };
    parseMethod = 'critical_error';
  }
  
  return { data, success: parseSuccess, method: parseMethod, contentType };
}

function parseUrlEncodedSafe(dataString) {
  const params = {};
  dataString.split('&').forEach(pair => {
    const equalIndex = pair.indexOf('=');
    if (equalIndex > 0) {
      const key = decodeURIComponent(pair.substring(0, equalIndex));
      const value = decodeURIComponent(pair.substring(equalIndex + 1).replace(/\+/g, ' '));
      params[key] = value;
    }
  });
  return params;
}

// Comprehensive extraction functions with debugging
function extractMultipleCompanies(data) {
  const companies = [];
  console.log('🏢 COMPANY EXTRACTION DEBUG:', JSON.stringify(data));
  
  // Direct fields
  ['company_name', 'company'].forEach(key => {
    if (data[key]) {
      console.log(`Found company key "${key}": "${data[key]}"`);
      splitCSV(data[key]).forEach((company, index) => {
        if (company) {
          companies.push(company);
          console.log(`Added company ${index + 1}: "${company}"`);
        }
      });
    }
  });
  
  // Numbered fields
  for (let i = 1; i <= 5; i++) {
    const fields = [`company${i}`, `company_${i}`, `company_name_${i}`];
    fields.forEach(field => {
      if (data[field]) {
        console.log(`Found numbered company field "${field}": "${data[field]}"`);
        splitCSV(data[field]).forEach((company, index) => {
          if (company) companies.push(company);
        });
      }
    });
  }
  
  // Array extractions
  ['companies', 'appointments'].forEach(arrayKey => {
    if (Array.isArray(data[arrayKey])) {
      console.log(`Found ${arrayKey} array:`, data[arrayKey]);
      data[arrayKey].forEach((item, itemIndex) => {
        const value = item.company || item.company_name || (arrayKey === 'companies' ? item : null);
        if (value) {
          console.log(`Processing ${arrayKey}[${itemIndex}] company: "${value}"`);
          splitCSV(value).forEach((company, companyIndex) => {
            if (company) {
              companies.push(company);
              console.log(`Added company from ${arrayKey}[${itemIndex}][${companyIndex}]: "${company}"`);
            }
          });
        }
      });
    }
  });
  
  console.log('🏢 Final companies (with duplicates preserved):', companies);
  console.log(`🏢 Total companies found: ${companies.length}`);
  return companies;
}

function extractMultipleDates(data) {
  const dates = [];
  console.log('📅 DATE EXTRACTION DEBUG:', JSON.stringify(data));
  
  const dateKeys = [
    'selected_date', 'booking_date', 'date',
    'date1', 'date2', 'date3', 'date4', 'date5',
    'date_1', 'date_2', 'date_3', 'date_4', 'date_5',
    'booking_date_1', 'booking_date_2', 'booking_date_3',
    'selected_date_1', 'selected_date_2', 'selected_date_3'
  ];
  
  dateKeys.forEach(key => {
    if (data[key]) {
      console.log(`Found date key "${key}": "${data[key]}"`);
      splitCSV(data[key]).forEach((date, index) => {
        if (date) {
          dates.push(date);
          console.log(`Added date ${index + 1}: "${date}"`);
        }
      });
    }
  });
  
  ['dates', 'appointments'].forEach(arrayKey => {
    if (Array.isArray(data[arrayKey])) {
      console.log(`Found ${arrayKey} array:`, data[arrayKey]);
      data[arrayKey].forEach((item, itemIndex) => {
        const value = item.date || item.booking_date || item.selected_date || (arrayKey === 'dates' ? item : null);
        if (value) {
          console.log(`Processing ${arrayKey}[${itemIndex}] date: "${value}"`);
          splitCSV(value).forEach((date, dateIndex) => {
            if (date) {
              dates.push(date);
              console.log(`Added date from ${arrayKey}[${itemIndex}][${dateIndex}]: "${date}"`);
            }
          });
        }
      });
    }
  });
  
  console.log('📅 Final dates (with duplicates preserved):', dates);
  console.log(`📅 Total dates found: ${dates.length}`);
  return dates;
}

function extractMultipleTimes(data) {
  const times = [];
  console.log('🕐 TIME EXTRACTION DEBUG:', JSON.stringify(data));
  
  const timeKeys = [
    'selected_time', 'booking_time', 'time',
    'time1', 'time2', 'time3', 'time4', 'time5',
    'time_1', 'time_2', 'time_3', 'time_4', 'time_5',
    'booking_time_1', 'booking_time_2', 'booking_time_3',
    'selected_time_1', 'selected_time_2', 'selected_time_3'
  ];
  
  timeKeys.forEach(key => {
    if (data[key]) {
      console.log(`Found time key "${key}": "${data[key]}"`);
      splitCSV(data[key]).forEach((time, index) => {
        if (time) {
          times.push(time);
          console.log(`Added time ${index + 1}: "${time}"`);
        }
      });
    }
  });
  
  ['times', 'appointments'].forEach(arrayKey => {
    if (Array.isArray(data[arrayKey])) {
      console.log(`Found ${arrayKey} array:`, data[arrayKey]);
      data[arrayKey].forEach((item, itemIndex) => {
        const value = item.time || item.booking_time || item.selected_time || (arrayKey === 'times' ? item : null);
        if (value) {
          console.log(`Processing ${arrayKey}[${itemIndex}] time: "${value}"`);
          splitCSV(value).forEach((time, timeIndex) => {
            if (time) {
              times.push(time);
              console.log(`Added time from ${arrayKey}[${itemIndex}][${timeIndex}]: "${time}"`);
            }
          });
        }
      });
    }
  });
  
  console.log('🕐 Final times (with duplicates preserved):', times);
  console.log(`🕐 Total times found: ${times.length}`);
  return times;
}

function buildCompanyDateTime(data) {
  const companies = extractMultipleCompanies(data);
  const dates = extractMultipleDates(data);
  const times = extractMultipleTimes(data);
  
  const maxLength = Math.max(companies.length, dates.length, times.length);
  const n = Math.min(3, Math.max(1, maxLength));
  
  return {
    companiesStr: padOrRepeat(companies, n).join('; '),
    datesStr: padOrRepeat(dates, n).join('; '),
    timesStr: padOrRepeat(times, n).join('; ')
  };
}

// Main webhook entry point with enhanced parsing
function doPost(e) {
  const startTime = Date.now();
  let data = {};
  
  // Initial logging
  try {
    const logSheet = getOrCreateLogSheet();
    logSheet.appendRow([
      Utilities.formatDate(new Date(), Session.getScriptTimeZone(), "MM/dd/yyyy HH:mm:ss"),
      'Webhook Received', 'processing', 'initial', 'System', 'Webhook',
      'Raw webhook data received', 'Starting processing...', '', '0ms'
    ]);
  } catch (logError) {
    console.error('Failed to log webhook receipt:', logError);
  }
  
  try {
    const parseResult = parseIncomingData(e);
    data = parseResult.data;
    const parseSuccess = parseResult.success;
    
    // Log parsed data
    const sessionForLog = data.session_id || 'no-session';
    const customerForLog = data.customer_name || data.name || 'Anonymous';
    const serviceForLog = data.service || data.service_type || 'Unknown';
    
    try {
      const logSheet = getOrCreateLogSheet();
      logSheet.appendRow([
        Utilities.formatDate(new Date(), Session.getScriptTimeZone(), "MM/dd/yyyy HH:mm:ss"),
        'Data Parsed', parseSuccess ? 'success' : 'warning', sessionForLog, customerForLog, serviceForLog,
        JSON.stringify(data).substring(0, 500),
        `Parsed via ${parseResult.method}: ${sessionForLog}`,
        parseSuccess ? '' : `Parse error: ${parseResult.method}`,
        `${Date.now() - startTime}ms`
      ]);
    } catch (logError) {}
    
    const result = processWebhookDataEnhanced(data, true);
    const responseTime = Date.now() - startTime;
    
    logWebhookActivity({
      timestamp: new Date(),
      eventType: result.isComplete ? 'Complete Booking' : 'Incomplete Lead',
      status: 'success',
      sessionId: result.sessionId || 'unknown',
      customerName: customerForLog,
      service: serviceForLog,
      dataReceived: JSON.stringify(data).substring(0, 500),
      processingResult: JSON.stringify(result).substring(0, 150),
      errorMessage: '',
      responseTime: responseTime
    });
    
    return ContentService.createTextOutput(JSON.stringify({success: true, result}))
      .setMimeType(ContentService.MimeType.JSON);
      
  } catch (error) {
    const responseTime = Date.now() - startTime;
    logWebhookActivity({
      timestamp: new Date(), eventType: 'Processing Error', status: 'error',
      sessionId: data?.session_id || 'unknown', customerName: data?.customer_name || 'Unknown',
      service: data?.service || 'Unknown', dataReceived: JSON.stringify(data || {}).substring(0, 500),
      processingResult: '', errorMessage: error.toString(), responseTime: responseTime
    });
    
    return ContentService.createTextOutput(JSON.stringify({success: false, error: error.toString()}))
      .setMimeType(ContentService.MimeType.JSON);
  }
}

// Enhanced webhook data processing
function processWebhookDataEnhanced(data, logActivity = false) {
  if (!data || typeof data !== 'object') {
    throw new Error('Invalid data received');
  }
  
  // CRITICAL SESSION MANAGEMENT
  if (data.session_completed === true || data.session_terminated === true) {
    console.log(`🚫 Session ${data.session_id} terminated - blocking incomplete_lead processing`);
    if (data.action !== 'complete_booking') {
      return {
        sessionId: data.session_id, action: 'blocked_terminated_session',
        message: 'Session terminated - incomplete webhooks blocked', isComplete: false
      };
    }
  }
  
  let sessionId = data.session_id;
  if (!sessionId || sessionId === 'no-session' || sessionId === 'unknown') {
    sessionId = generateSessionId();
  }
  
  const bookingId = data.booking_id || data.id;
  const leadAnalysis = analyzeLead(data);
  const mainSheet = getOrCreateMainSheet();
  const headerMap = buildHeaderColumnMap(mainSheet);
  const existingRow = findExistingEntryEnhanced(mainSheet, sessionId, bookingId);
  const dataObj = buildDataObject(data, leadAnalysis, sessionId);
  
  let rowNumber, action;
  
  if (existingRow > 0) {
    const hasUpdates = mergeRowDataByHeaders(mainSheet, existingRow, dataObj, headerMap);
    rowNumber = existingRow;
    action = hasUpdates ? 'updated' : 'no_changes';
    
    if (hasUpdates) {
      const numCols = Math.max(mainSheet.getLastColumn(), Object.keys(headerMap).length);
      mainSheet.getRange(rowNumber, 1, 1, numCols).setBackground("#fff2cc");
    }
  } else {
    rowNumber = mainSheet.getLastRow() + 1;
    const maxCol = Math.max(...Object.values(headerMap));
    const rowDataArray = new Array(maxCol).fill('');
    
    for (const header in dataObj) {
      const colIndex = headerMap[header];
      if (colIndex && colIndex <= maxCol) {
        rowDataArray[colIndex - 1] = dataObj[header];
      }
    }
    
    mainSheet.getRange(rowNumber, 1, 1, maxCol).setValues([rowDataArray]);
    action = 'created';
    
    const bgColor = leadAnalysis.isComplete ? "#d9ead3" : "#fce5cd";
    const numCols = Math.max(mainSheet.getLastColumn(), Object.keys(headerMap).length);
    mainSheet.getRange(rowNumber, 1, 1, numCols).setBackground(bgColor);
  }
  
  return {
    sessionId, bookingId, rowNumber, action,
    leadType: leadAnalysis.leadType, status: leadAnalysis.status,
    completionPercentage: leadAnalysis.completionPercentage,
    isComplete: leadAnalysis.isComplete
  };
}

// Build data object with enhanced field mapping
function buildDataObject(data, leadAnalysis, sessionId) {
  const timestamp = new Date();
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
    'Completion %': leadAnalysis.completionPercentage || data.completion_percentage || 0,
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

function getBookingIdValue(data, sessionId) {
  if (data.action === 'complete_booking') {
    const bookingId = data.booking_id || data.id;
    if (bookingId && String(bookingId).trim() !== '' && String(bookingId) !== 'undefined') {
      console.log(`✅ Complete booking detected - using booking_id: ${bookingId}`);
      return String(bookingId);
    }
  }
  return data.booking_id || data.id || sessionId || '';
}

function buildNotesField(data) {
  return [data.notes, data.special_notes, data.message, data.specifications]
    .filter(note => note && note.toString().trim() !== '').join('; ') || '';
}

// Analyze lead completion and status
function analyzeLead(data) {
  const hasBookingId = !!(data.booking_id || data.id);
  const hasCompleteCustomerInfo = !!(data.customer_name && data.customer_email && data.customer_phone);
  const hasAppointmentInfo = !!(data.booking_date || data.selected_date || data.date) && (data.booking_time || data.selected_time || data.time);
  const hasServiceInfo = !!(data.service_type || data.service);
  
  const isCompleteBookingAction = data.action === 'complete_booking';
  const isCompleteLeadStatus = data.lead_status === 'Complete' || data.lead_status === 'Converted';
  
  // Calculate completion percentage
  const requiredFields = ['service', 'customer_name', 'customer_email', 'customer_phone', 'customer_address', 'booking_date', 'booking_time', 'company'];
  const completedFields = requiredFields.filter(field => {
    const value = data[field] || data[field.replace('customer_', '')] || data[field.replace('booking_', 'selected_')] || data[field.replace('booking_', '')];
    return value && value.toString().trim() !== '';
  }).length;
  
  const completionPercentage = Math.round((completedFields / requiredFields.length) * 100);
  
  let leadType, status;
  
  if (isCompleteBookingAction) {
    leadType = 'Complete Booking';
    status = 'Converted';
  } else if (isCompleteLeadStatus) {
    leadType = 'Complete Booking';
    status = 'Converted';
  } else if (hasBookingId && hasCompleteCustomerInfo && hasAppointmentInfo) {
    leadType = 'Complete Booking';
    status = 'Converted';
  } else if (hasServiceInfo && (hasCompleteCustomerInfo || completionPercentage >= 50)) {
    leadType = 'Qualified Lead';
    status = 'In Progress';
  } else if (hasServiceInfo) {
    leadType = 'Initial Lead';
    status = 'Started';
  } else {
    leadType = 'Anonymous Visitor';
    status = 'Browsing';
  }
  
  const finalCompletionPercentage = (leadType === 'Complete Booking') ? 100 : completionPercentage;
  
  return {
    leadType, status,
    completionPercentage: finalCompletionPercentage,
    isComplete: leadType === 'Complete Booking'
  };
}

function determineFormStep(data) {
  if (data.action === 'complete_booking') return 'Step 4: Confirmation';
  if ((data.booking_date || data.selected_date) && (data.booking_time || data.selected_time)) return 'Step 4: Confirmation';
  if (data.customer_name && data.customer_email) return 'Step 3: Contact Info';
  if (data.selected_date || data.booking_date) return 'Step 2: Date Selection';
  if (data.service_type || data.service) return 'Step 1: Service Selection';
  return 'Initial Visit';
}

function calculateLeadScore(data, leadAnalysis) {
  if (data.action === 'complete_booking' || leadAnalysis?.isComplete) return 100;
  
  let score = leadAnalysis?.completionPercentage || data.completion_percentage || 0;
  const serviceScores = {'ADU': 95, 'Roof': 90, 'Kitchen': 85, 'Bathroom': 80, 'Siding': 75, 'Windows': 70, 'Decks': 65};
  const service = data.service || data.service_type || '';
  if (serviceScores[service]) score = Math.max(score, serviceScores[service]);
  
  const utmSource = (data.utm_source || '').toLowerCase();
  if (utmSource.includes('google')) score += 10;
  else if (utmSource.includes('facebook')) score += 8;
  else if (utmSource) score += 5;
  
  if (data.customer_email || data.email) score += 5;
  if (data.customer_phone || data.phone) score += 5;
  if (data.customer_address || data.address) score += 5;
  
  return Math.min(100, Math.max(0, score));
}

// Build header mapping with performance caching
function buildHeaderColumnMap(sheet) {
  const cacheKey = 'headerMap_' + sheet.getSheetName();
  let headerMap = PropertiesService.getScriptProperties().getProperty(cacheKey);
  
  if (headerMap) {
    try {
      headerMap = JSON.parse(headerMap);
      if (Object.keys(headerMap).length === sheet.getLastColumn()) return headerMap;
    } catch (e) {}
  }
  
  headerMap = {};
  let existingHeaders = [];
  if (sheet.getLastRow() >= 1) {
    existingHeaders = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];
  }
  
  existingHeaders.forEach((header, index) => {
    if (header) headerMap[header] = index + 1;
  });
  
  const missingHeaders = BOOKING_HEADERS.filter(h => !headerMap[h]);
  if (missingHeaders.length > 0) {
    const startCol = existingHeaders.length + 1;
    const headerRange = sheet.getRange(1, startCol, 1, missingHeaders.length);
    headerRange.setValues([missingHeaders]);
    
    missingHeaders.forEach((header, index) => {
      headerMap[header] = startCol + index;
    });
    
    headerRange.setFontWeight("bold").setBackground("#4285f4").setFontColor("#ffffff");
  }
  
  try {
    PropertiesService.getScriptProperties().setProperty(cacheKey, JSON.stringify(headerMap));
  } catch (e) {}
  
  return headerMap;
}

function mergeRowDataByHeaders(sheet, rowNumber, newDataObj, headerMap) {
  const numCols = Math.max(sheet.getLastColumn(), Object.keys(headerMap).length);
  const existingRow = sheet.getRange(rowNumber, 1, 1, numCols).getValues()[0];
  
  const updates = {};
  let hasUpdates = false;
  
  for (const header in newDataObj) {
    const colIndex = headerMap[header];
    if (!colIndex) continue;
    
    const newValue = newDataObj[header];
    const existingValue = existingRow[colIndex - 1];
    
    let shouldUpdate = false;
    
    if (['Timestamp', 'Last Updated'].includes(header)) {
      shouldUpdate = true;
    } else if (header === 'Lead Score') {
      shouldUpdate = (parseInt(newValue) || 0) > (parseInt(existingValue) || 0);
    } else if (['Customer Name', 'Customer Email', 'Customer Phone'].includes(header)) {
      shouldUpdate = newValue !== undefined && newValue !== null;
    } else if (!existingValue || existingValue.toString().trim() === '') {
      shouldUpdate = newValue !== undefined && newValue !== null;
    } else if (newValue !== undefined && newValue !== null && newValue !== existingValue) {
      shouldUpdate = true;
    }
    
    if (shouldUpdate) {
      updates[colIndex] = newValue;
      hasUpdates = true;
    }
  }
  
  if (hasUpdates) {
    Object.entries(updates).forEach(([colIndex, value]) => {
      sheet.getRange(rowNumber, parseInt(colIndex)).setValue(value);
    });
  }
  
  return hasUpdates;
}

function findExistingEntryEnhanced(sheet, sessionId, bookingId) {
  if (!sessionId && !bookingId) return -1;
  
  const dataRange = sheet.getDataRange();
  if (dataRange.getNumRows() <= 1) return -1;
  
  const values = dataRange.getValues();
  const headers = values[0];
  const sessionIdCol = headers.indexOf('Session ID');
  const bookingIdCol = headers.indexOf('Booking ID');
  
  for (let i = 1; i < values.length; i++) {
    const row = values[i];
    if (bookingId && bookingIdCol >= 0 && row[bookingIdCol] === bookingId) return i + 1;
    if (sessionId && sessionIdCol >= 0 && row[sessionIdCol] === sessionId) return i + 1;
  }
  
  return -1;
}

// Enhanced webhook activity logging
function logWebhookActivity(logData) {
  try {
    const logSheet = getOrCreateLogSheet();
    const timestamp = logData.timestamp || new Date();
    const formattedTimestamp = Utilities.formatDate(timestamp, Session.getScriptTimeZone(), "MM/dd/yyyy HH:mm:ss");
    
    const logRow = [
      formattedTimestamp, logData.eventType || 'Unknown Event', logData.status || 'unknown',
      logData.sessionId || 'no-session', logData.customerName || 'Anonymous', logData.service || 'Unknown Service',
      logData.dataReceived || 'No data', logData.processingResult || 'No result',
      logData.errorMessage || '', `${logData.responseTime || 0}ms`
    ];
    
    logSheet.appendRow(logRow);
    
    const rowNum = logSheet.getLastRow();
    const rowRange = logSheet.getRange(rowNum, 1, 1, logRow.length);
    
    if (logData.status === 'error') {
      rowRange.setBackground('#ffebee');
    } else if (logData.status === 'success') {
      rowRange.setBackground('#e8f5e8');
    } else {
      rowRange.setBackground('#fff3e0');
    }
    
    if (logSheet.getLastRow() > CONFIG.MAX_LOG_ROWS) {
      const rowsToDelete = logSheet.getLastRow() - CONFIG.MAX_LOG_ROWS + 50;
      if (rowsToDelete > 0) logSheet.deleteRows(2, rowsToDelete);
    }
    
  } catch (error) {
    console.error('Logging failed:', error.toString());
    try {
      const ss = SpreadsheetApp.getActiveSpreadsheet();
      let errorSheet = ss.getSheetByName('Error_Log');
      if (!errorSheet) {
        errorSheet = ss.insertSheet('Error_Log');
        errorSheet.getRange(1, 1, 1, 3).setValues([['Timestamp', 'Error', 'Data']]);
      }
      errorSheet.appendRow([new Date(), error.toString(), JSON.stringify(logData)]);
    } catch (e) {}
  }
}

function getOrCreateMainSheet() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  let sheet = ss.getSheetByName(CONFIG.MAIN_SHEET_NAME);
  
  if (!sheet) {
    sheet = ss.insertSheet(CONFIG.MAIN_SHEET_NAME);
    initializeSheetHeaders(sheet, BOOKING_HEADERS);
  }
  
  return sheet;
}

function getOrCreateLogSheet() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  let sheet = ss.getSheetByName(CONFIG.WEBHOOK_LOG_SHEET);
  
  if (!sheet) {
    sheet = ss.insertSheet(CONFIG.WEBHOOK_LOG_SHEET);
  }
  
  if (sheet.getLastRow() === 0 || !hasCorrectHeaders(sheet, WEBHOOK_LOG_HEADERS)) {
    initializeLogSheetHeaders(sheet);
  }
  
  return sheet;
}

function hasCorrectHeaders(sheet, expectedHeaders) {
  if (sheet.getLastRow() === 0) return false;
  try {
    const headerRow = sheet.getRange(1, 1, 1, expectedHeaders.length).getValues()[0];
    return expectedHeaders.every((header, i) => headerRow[i] === header);
  } catch {
    return false;
  }
}

function initializeLogSheetHeaders(sheet) {
  sheet.clear();
  sheet.getRange(1, 1, 1, WEBHOOK_LOG_HEADERS.length).setValues([WEBHOOK_LOG_HEADERS]);
  
  const headerRange = sheet.getRange(1, 1, 1, WEBHOOK_LOG_HEADERS.length);
  headerRange.setFontWeight("bold").setBackground("#1a73e8").setFontColor("#ffffff");
  
  [150, 120, 80, 150, 150, 120, 300, 200, 200, 100].forEach((width, i) => {
    sheet.setColumnWidth(i + 1, width);
  });
  
  sheet.setFrozenRows(1);
}

function initializeSheetHeaders(sheet, headers) {
  sheet.getRange(1, 1, 1, headers.length).setValues([headers]);
  const headerRange = sheet.getRange(1, 1, 1, headers.length);
  headerRange.setFontWeight("bold").setBackground("#4285f4").setFontColor("#ffffff");
  sheet.setFrozenRows(1);
  sheet.autoResizeColumns(1, headers.length);
}

function generateSessionId() {
  return 'session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 5);
}

function cleanupOldLogs(sheet) {
  const currentRows = sheet.getLastRow();
  const rowsToDelete = currentRows - CONFIG.MAX_LOG_ROWS + 50;
  if (rowsToDelete > 0) {
    sheet.deleteRows(2, rowsToDelete);
  }
}

// Setup and maintenance functions
function setupSheets() {
  const results = { timestamp: new Date(), errors: [] };
  
  try {
    const mainSheet = getOrCreateMainSheet();
    const logSheet = getOrCreateLogSheet();
    results.mainSheetCreated = true;
    results.logSheetCreated = true;
    results.headersInitialized = true;
    console.log('✅ Sheet setup completed successfully!');
  } catch (error) {
    results.errors.push(error.toString());
  }
  
  return results;
}

function getWebhookStats() {
  try {
    const logSheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(CONFIG.WEBHOOK_LOG_SHEET);
    if (!logSheet) return { error: 'Log sheet not found' };
    
    const data = logSheet.getDataRange().getValues();
    if (data.length <= 1) return { total: 0, success: 0, errors: 0 };
    
    const rows = data.slice(1);
    const total = rows.length;
    const success = rows.filter(row => row[2] === 'success').length;
    const errors = rows.filter(row => row[2] === 'error').length;
    
    const yesterday = new Date(Date.now() - 24 * 60 * 60 * 1000);
    const recent = rows.filter(row => new Date(row[0]) > yesterday).length;
    
    return {
      total, success, errors,
      successRate: total > 0 ? Math.round((success / total) * 100) : 0,
      recentActivity: recent
    };
  } catch (error) {
    return { error: error.toString() };
  }
}

function performMaintenance() {
  const results = { timestamp: new Date(), cleanupPerformed: false };
  
  try {
    const mainSheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(CONFIG.MAIN_SHEET_NAME);
    const logSheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(CONFIG.WEBHOOK_LOG_SHEET);
    
    if (mainSheet) results.mainSheetRows = mainSheet.getLastRow();
    if (logSheet) {
      results.logSheetRows = logSheet.getLastRow();
      if (results.logSheetRows > CONFIG.MAX_LOG_ROWS) {
        const rowsToDelete = results.logSheetRows - CONFIG.MAX_LOG_ROWS + 50;
        logSheet.deleteRows(2, rowsToDelete);
        results.cleanupPerformed = true;
      }
    }
  } catch (error) {
    results.error = error.toString();
  }
  
  return results;
}