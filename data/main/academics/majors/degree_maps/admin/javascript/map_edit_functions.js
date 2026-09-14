$(document).ready(function () {
    // -------------------------------
    // Setup
    // -------------------------------

    let degreeMapId = $("#degree_map_id").val();
    initializeDragAndDrop(); // Initialize on first page load
    initializeTippy();

    $(document).on("change", "#degree_map_id", function () {
        degreeMapId = $(this).val();
    });

    // Helper: Generic AJAX Call
    function ajaxCall(options) {
        $.ajax({
            url: options.url,
            method: options.method || 'GET',
            data: options.data || {},
            dataType: options.dataType || 'json',
            success: function (response) {
                if (typeof options.success === 'function') {
                    options.success(response);
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX error:', error);
                if (typeof options.error === 'function') {
                    options.error(xhr, status, error);
                }
            }
        });
    }

    function refreshCourseTable() {
        ajaxCall({
            url: "ajax/get_degree_map.php", // New AJAX endpoint
            method: "GET",
            data: { degree_map_id: degreeMapId }, // Pass current map ID
            dataType: "html", // Expect HTML to replace content
            success: function (response) {
                $("#search_results").html(response); // Replace content dynamically
                initializeDragAndDrop(); // Reinitialize sortable after replacing content
                initializeTippy();
            }
        });
    }

    function initializeTippy(){
        tippy('.footnote-link', {
            allowHTML: true,
            placement: 'bottom',
            trigger: 'mouseenter focus'
          });
    }

     // -------------------------------
    // 7️⃣ Course Reordering with Drag-and-Drop
    // -------------------------------
    function initializeDragAndDrop() {
        $(".semester-list").sortable({
            connectWith: ".semester-list",
            handle: ".drag-handle",
            placeholder: "sortable-placeholder",
            update: function (event, ui) {
                let updatedOrder = [];
    
                $(".semester-list").each(function () {
                    let semester = $(this).data("semester"); // Get new semester
                    let year = $(this).data("year"); // Get new year
                    let courseOrder = [];
    
                    $(this).find(".course-item").each(function (index) {
                        let courseId = $(this).data("course-id");
                        if (!courseId) return; // Skip if no valid course ID
    
                        courseOrder.push({
                            id: courseId,
                            order: index + 1, // Assign new order based on position
                            semester: semester, // Assign new semester
                            year: year // Assign new year
                        });
    
                        // Update dataset attributes for immediate feedback
                        $(this).attr("data-semester", semester);
                        $(this).attr("data-year", year);
                    });
    
                    updatedOrder.push(...courseOrder);
                });
    
                // Send updated order, including year and semester, to backend
                ajaxCall({
                    url: "ajax/save_course_order.php",
                    method: "POST",
                    data: { updatedOrder: JSON.stringify(updatedOrder) },
                    success: function (response) {
                        if (!response.success) {
                            alert("Error updating course order: " + response.error);
                        }
                    }
                });
            }
        }).disableSelection();
    }
    
    function initializeDragAndDropFootnotes() {
        $(".footnotes-list").sortable({
            connectWith: ".footnotes-list",
            handle: ".drag-handle",
            placeholder: "sortable-placeholder",
            update: function (event, ui) {
                
                let updatedOrder = [];
    
                $(".footnotes-list").each(function () {
                    let footnoteOrder = [];
    
                    $(this).find(".footnote-container").each(function (index) {
                        let footnoteId = $(this).data("footnote-id");
                        let footnoteNote = $(this).find(".footnote-note");
                        
                        if (!footnoteId || footnoteNote.length === 0) {
                            // console.warn("Skipping footnote - ID or note element missing:", { footnoteId, elementFound: footnoteNote.length });
                        } else {
                            footnoteOrder.push({
                                id: footnoteId,
                                order: index + 1,
                                note: footnoteNote.val().trim(),
                            });
                        }
        
                    });
    
                    updatedOrder.push(...footnoteOrder);
                });
                // Send updated order, including year and semester, to backend
                ajaxCall({
                    url: "ajax/save_map_footnotes.php",
                    method: "POST",
                    data: { footnotes: JSON.stringify(updatedOrder), degree_map_id:degreeMapId },
                    success: function (response) {
                        if (!response.success) {
                            alert("Error updating course order: " + response.error);
                        }
                    }
                });
            }
        }).disableSelection();
    }

   // -------------------------------
    // Select2 & Autocomplete
    // -------------------------------
    function initializeSelect2() {
        $(".select2").select2({
            width: "100%",
            placeholder: "Select footnotes",
            allowClear: true
        });
    }


    function initializeAutocomplete() {
        $("#course_info").autocomplete({
            source: function (request, response) {
                $.getJSON("ajax/course_autocomplete.php", { term: request.term }, function (data) {
                    response(data);
                });
            },
            minLength: 2,
            select: function (event, ui) {
                $("#course_info").val(ui.item.value);
                $("#hours").val(ui.item.hours);
                $("#scbcrse_subj_code").val(ui.item.scbcrse_subj_code);
                $("#cbcrse_crse_numb").val(ui.item.scbcrse_crse_numb);
                return false;
            }
        });
    }

    $("#footnotes").select2({
        ajax: {
            url: "ajax/get_footnotes.php",
            dataType: "json",
            delay: 250,
            data: function () {
                return { degree_map_id: degreeMapId }; // Pass the map ID
            },
            processResults: function (data) {
                if (!Array.isArray(data)) {
                    console.error("Expected array but got:", typeof data, data);
                    return { results: [] };
                }
                return {
                    results: data.map(f => ({ id: f.id, text: f.text }))
                };
            }
        },
        multiple: true,
        width: "100%",
        placeholder: "Select footnotes",
        allowClear: true
    });




    // -------------------------------
    // -------------------------------
    // -------------------------------
    // Course Handling
    // -------------------------------

    
    function openCourseModal(courseId, degreeMapId, year, semester) {
        let requestData = { 
            degree_map_id: degreeMapId,
            year: year,
            semester: semester
         };
        
        if (courseId !== "new") {
            requestData.id = courseId;  // Only add ID if it’s an existing course
        } 
    
        $.ajax({
            url: "ajax/edit_course.php",
            method: "POST",
            data: requestData,
            dataType: "json",
            success: function (response) {

                if (response.error) {
                    alert("Error: " + response.error);
                    return;
                }
            
                // Ensure modal data exists
                if (!response.modal) {
                    alert("Error: Missing modal content from server.");
                    return;
                }
                // Remove existing modal only if it exists
                if ($("#editCourseModal").length) {
                    $("#editCourseModal").remove();
                }
            
                // Inject and initialize modal
                $("body").append(response.modal);
                $("#editCourseModal").dialog({
                    modal: true,
                    width: 800,
                    open: function () {
                        initializeAutocomplete();
                        initializeSelect2();
                        $( "#advanced" ).accordion({
                            collapsible: true,
                            active: false
                          });
                    }
                });
            
                if (response.error) {
                    alert("Error: " + response.error);
                    return;
                }
    
            
                // Attach event listeners
                $("#saveCourseBtn").on("click", function () {
                    saveCourseDetails();
                });
    
                $("#cancelCourseBtn").on("click", function () {
                    $("#editCourseModal").dialog("close");
                });
    
                $("#deleteCourseBtn").on("click", function () {
                    if (!confirm("Are you sure you want to delete this course?")) return;
                    $.ajax({
                        url: "ajax/delete_course.php",
                        method: "POST",
                        data: { course_id: courseId },
                        success: function () {
                            $("#editCourseModal").dialog("close");
                            refreshCourseTable();
                        }
                    });
                });
            }
        });
    }

    // -------------------------------
    // Course Autocomplete
    // -------------------------------
   
    $("#editCourseModal").on("dialogopen", function () {
        $("#course_info").autocomplete({
            source: function(request, response) {
                $.getJSON("ajax/course_autocomplete.php", { term: request.term }, function(data) {
                    response(data);
                });
            },
            minLength: 2,
            select: function(event, ui) {
                $("#editCourseModal #course_info").val(ui.item.value);
                $("#editCourseModal #hours").val(ui.item.hours);
                $("#editCourseModal #scbcrse_subj_code").val(ui.item.scbcrse_subj_code);
                $("#editCourseModal #scbcrse_crse_numb").val(ui.item.scbcrse_crse_numb);
                
                return false;
            }
        });
    });
   // -------------------------------
    // Remove Course Handler
    // -------------------------------
    $(document).on("click", ".remove-course-btn", function () {
        if (!confirm("Are you sure you want to remove this course?")) return;

        const courseId = $(this).data("course-id");
        if (courseId) {
            $('<input>', {
                type: "hidden",
                name: "remove_course_ids[]",
                value: courseId
            }).appendTo("#degree_map_form");
        }
        $(this).closest(".container").remove();
    });

   // -------------------------------
    // Handle Editing an Existing Course
    // -------------------------------
    $(document).on("click", ".edit_course", function (e) {
        e.preventDefault();
        let courseId = $(this).data("course-id");
        let degreeMapId = $("#degree_map_id").val();
        let $semesterList = $(this).closest(".semester-list");
        let year = $semesterList.data("year");
        let semester = $semesterList.data("semester");

        openCourseModal(courseId, degreeMapId, year, semester);
    });

    // -------------------------------
    // Handle Creating a New Course
    // -------------------------------
    $(document).off("click", ".new_course").on("click", ".new_course", function (e) {
        e.preventDefault();

        let degreeMapId = $("#degree_map_id").val();
        let $button = $(this); // Ensure correct context
        let year = $button.attr("data-year"); // Use .attr() to ensure correct data retrieval
        let semester = $button.attr("data-semester");

        if (!year || !semester) {
            console.warn("Year or semester missing on new_course button!"); 
            return; // Prevents unnecessary AJAX call
        }

        openCourseModal("new", degreeMapId, year, semester);
    });

    // -------------------------------
    // Save Course
    // -------------------------------

    function saveCourseDetails() {
        let formData = $("#editCourseForm").serialize();
    
        ajaxCall({
            url: "ajax/save_course.php",
            method: "POST",
            data: formData,
            dataType: "json",
            success: function (response) {
                if (response.success) {
                    $("#editCourseModal").dialog("close");
                    refreshCourseTable();
                } else {
                    alert("Error saving course: " + response.error);
                }
            },
            error: function () {
                alert("Failed to save course.");
            }
        });
    }

    // Allow pressing "Enter" to trigger "Save Changes"
    $("#editCourseForm").on("keypress", function (e) {
        if (e.which === 13) { // Enter key
            e.preventDefault();
            $(".ui-button-primary").trigger("click"); // Explicitly target Save button
        }
    });    
    
    
    // -------------------------------
    // -------------------------------
    // -------------------------------
    // Footnote Setup
    // -------------------------------

    // -------------------------------
    // Open Footnote Modal
    // -------------------------------
    $(document).on("click", "#edit_map_footnotes", function (e) {
        e.preventDefault();
        let degreeId = $(this).data("map-id");
        // Fetch existing details via AJAX
        $.ajax({
            url: "ajax/edit_map_footnotes.php",
            method: "POST",
            data: { degree_map_id: degreeId },
            dataType: "json",
            success: function (response) {
                // console.log("AJAX Response:", response); // Check if response is valid JSON
                if (response.error) {
                    alert("Error: " + response.error);
                    return;
                }
                // Ensure modal data exists
                if (!response.modal) {
                    alert("Error: Missing modal content from server.");
                    return;
                }
                // console.log("Modal HTML:", response.modal);
                $(".ui-dialog-content").dialog("close"); // Close any open modals
                if ($("#editFootnotesModal").length) {
                    $("#editFootnotesModal").remove();
                }
                // Inject and initialize modal
                $("body").append(response.modal);
                $("#editFootnotesModal").dialog({
                    modal: true,
                    width: 1000,
                    open: function () {
                        initializeDragAndDropFootnotes();
                    }
                });
                if (response.error) {
                    alert("Error: " + response.error);
                    return;
                }
                // Attach event listeners
                $("#SaveFootnotesBtn").one("click", function () {
                    saveFootnotes();
                });
            }
        });
    });

    // -------------------------------
    // Add new footnote
    // -------------------------------

    $(document).on("click", "#addFootnoteBtn", function (e) {
        e.preventDefault();
        const newFootnoteId = "new_" + Date.now();
        const footnoteCount = $("#footnotesContainer .footnote-container").length;
        const newOrder = footnoteCount + 1;

        const newFootnoteHtml = `
				<li class="footnote-container" data-footnote-id="${newFootnoteId}" data-order="${newOrder}">
					<span class="drag-handle cursor-move ui-sortable-handle">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6"><path fill-rule="evenodd" d="M6.97 2.47a.75.75 0 0 1 1.06 0l4.5 4.5a.75.75 0 0 1-1.06 1.06L8.25 4.81V16.5a.75.75 0 0 1-1.5 0V4.81L3.53 8.03a.75.75 0 0 1-1.06-1.06l4.5-4.5Zm9.53 4.28a.75.75 0 0 1 .75.75v11.69l3.22-3.22a.75.75 0 1 1 1.06 1.06l-4.5 4.5a.75.75 0 0 1-1.06 0l-4.5-4.5a.75.75 0 1 1 1.06-1.06l3.22 3.22V7.5a.75.75 0 0 1 .75-.75Z" clip-rule="evenodd"></path></svg>
                    </span>
					<div class="content">
						<input type="hidden" name="footnotes[${newFootnoteId}][id]" value="${newFootnoteId}">
						<label class="control-label sr-only" for="id_footnote_${newFootnoteId}">Footnote</label>
						<textarea class="footnote-note" name="footnotes[${newFootnoteId}][note]" id="id_footnote_${newFootnoteId}"></textarea>
					</div>
					<a role="button" title="Remove Footnote ${newOrder}" class="remove-footnote-btn button" data-footnote-id="${newFootnoteId}">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6" style="width: 24px;" role="img" ><path fill-rule="evenodd" d="M16.5 4.478v.227a48.816 48.816 0 0 1 3.878.512.75.75 0 1 1-.256 1.478l-.209-.035-1.005 13.07a3 3 0 0 1-2.991 2.77H8.084a3 3 0 0 1-2.991-2.77L4.087 6.66l-.209.035a.75.75 0 0 1-.256-1.478A48.567 48.567 0 0 1 7.5 4.705v-.227c0-1.564 1.213-2.9 2.816-2.951a52.662 52.662 0 0 1 3.369 0c1.603.051 2.815 1.387 2.815 2.951Zm-6.136-1.452a51.196 51.196 0 0 1 3.273 0C14.39 3.05 15 3.684 15 4.478v.113a49.488 49.488 0 0 0-6 0v-.113c0-.794.609-1.428 1.364-1.452Zm-.355 5.945a.75.75 0 1 0-1.5.058l.347 9a.75.75 0 1 0 1.499-.058l-.346-9Zm5.48.058a.75.75 0 1 0-1.498-.058l-.347 9a.75.75 0 0 0 1.5.058l.345-9Z" clip-rule="evenodd" /></svg>
					</a>
				</li>
`;

        $("#footnotesContainer").append(newFootnoteHtml);
    });

    // -------------------------------
    // Remove footnote
    // -------------------------------
    $(document).on("click", ".remove-footnote-btn", function (e) {
        e.preventDefault();
        let footnoteID = $(this).data("footnote-id");
        $(this).closest(".footnote-container").remove();
        deleteFootnote(footnoteID);
    });

    function deleteFootnote(footnoteID){
        $.ajax({
            url: "ajax/delete_footnote.php",
            method: "POST",
            data: { remove_footnote:footnoteID, degree_map_id:degreeMapId },
            dataType: "json",
            success: function (response) {
                if (response.success) {
                } else {
                    alert("Error deleteing map footnotes: " + response.message);
                }
            },
            error: function () {
                alert("Failed to save map footnotes.");
            }
        });
    }

    // -------------------------------
    // Save map footnotes
    // -------------------------------

    function saveFootnotes() {
        let formData = $("#degree_footnotes_form").serialize();

        $.ajax({
            url: "ajax/save_map_footnotes.php",
            method: "POST",
            data: formData,
            dataType: "json",
            success: function (response) {
                if (response.success) {
                    $("#editFootnotesModal").dialog("close");
                    refreshCourseTable();
                } else {
                    alert("Error saving map footnotes: " + response.message);
                }
            },
            error: function () {
                alert("Failed to save map footnotes.");
            }
        });
    }

    // -------------------------------
    // -------------------------------
    // -------------------------------
    // Map Details Setup
    // -------------------------------

    // -------------------------------
    // Open Map Details Modal
    // -------------------------------
    $(document).on("click", "#edit_map_details", function (e) {
        e.preventDefault();
        let degreeId = $(this).data("map-id");
        $.ajax({
            url: "ajax/edit_map.php",
            method: "POST",
            data: { degree_map_id: degreeId },
            dataType: "json",
            success: function (response) {
                if (response.error) {
                    alert("Error: " + response.error);
                    return;
                }
                if (!response.modal) {
                    alert("Error: Missing modal content from server.");
                    return;
                }
                if ($("#editMapModal").length) {
                    $("#editMapModal").remove();
                }

                $("body").append(response.modal);
                $("#editMapModal").dialog({
                    modal: true,
                    width: 800,
                    open: function () {
                        updateDepartmentList();
                    }
                });
            
                if (response.error) {
                    alert("Error: " + response.error);
                    return;
                }
                $("#cancelMapBtn").one("click", function () {
                    $("#editMapModal").dialog("close");
                });
            }
        });
    });
    

    // -------------------------------
    // Open Map Details Modal
    // -------------------------------
    $(document).on("click", "#newMap", function (e) {
        e.preventDefault();
        let college = $("#selected_college").val();
        if ((college === "") || (college === 'all')){
            college = '';
        }

        $.ajax({
            url: "ajax/new_map.php",
            method: "POST",
            data: { college: college },
            dataType: "json",
            success: function (response) {
                if (response.error) {
                    alert("Error: " + response.error);
                    return;
                }
                if (!response.modal) {
                    alert("Error: Missing modal content from server.");
                    return;
                }
                if ($("#editMapModal").length) {
                    $("#editMapModal").remove();
                }
                $('#search_results').html('<div></div>');

                $("body").append(response.modal);
                $("#editMapModal").dialog({
                    modal: true,
                    width: 800,
                    open: function () {
						updateDepartmentList();
                    }
                });
            
                if (response.error) {
                    alert("Error: " + response.error);
                    return;
                }

                $("#cancelMapBtn").on("click", function () {
                    $("#editMapModal").dialog("close");
                });
            }
        });
    });


    // -------------------------------
    // Save Map Details 
    // -------------------------------


    function validateMapForm() {
        $(".is-invalid").removeClass("is-invalid"); // Clear previous validation styles
        let requiredFieldNames = ["major", "degree_type", "college"]; // Use `name` attributes instead of `id`
        let isValid = true;
        let missingFields = [];
    
        $("#degree_map_form").find("input, select, textarea").each(function () {
            let fieldName = $(this).attr("name"); // Get the name attribute
            if (requiredFieldNames.includes(fieldName)) {
                let value = $(this).val();
                
                if (!value || value.trim() === "") {
                    isValid = false;
                    $(this).addClass("is-invalid"); // Mark invalid
                    missingFields.push(fieldName);
                } else {
                    $(this).removeClass("is-invalid"); // Ensure valid fields aren’t marked red
                }
            }
        });
    
        if (!isValid) {
            alert("Please fill out all required fields: " + missingFields.join(", "));
            console.warn("Validation failed, stopping execution.");
        }
        
        return isValid; // Return true if valid, false if not
    }

    function submitMapForm() {
     console.trace("submitMapForm() is being called");
       let formData = $("#degree_map_form").serialize();
    
        $.ajax({
            url: "ajax/save_map_details.php",
            method: "POST",
            data: formData,
            dataType: "json",
            success: function (response) {
                if (response.degree_map_id) {
                    $("#degree_map_id").val(response.degree_map_id);
                    degreeMapId = response.degree_map_id;
                }
    
                if (response.success) {
                    setTimeout(function () {
                        $("#editMapModal").dialog("close");
                    }, 500);
                    refreshCourseTable();
                } else {
                    alert("Error saving map details: " + response.error);
                }
            },
            error: function () {
                alert("Failed to save map details.");
            }
        });
    }

    $(document).on("click", "#SaveMapBtn", function (e) {
        e.preventDefault();  // Stop default behavior
        console.log("Save button clicked");

        // Log all fields in #degree_map_form
        console.log("Logging all fields in #degree_map_form...");
        $("#degree_map_form").find("input, select, textarea").each(function () {
            console.log($(this).attr("name") + ": " + $(this).val());
        });

        let valid = validateMapForm();

        // Run validation
        if (!valid) {
            console.warn("Validation failed, stopping execution.");
            return false;  // **Explicitly stop execution**
        }else{
            console.log("Validation passed, proceeding with form submission.");
            submitMapForm(); // Only call this if validation passes
        }
    });

    // -------------------------------
    // update Department list
    // -------------------------------
    function updateDepartmentList() {
        console.log("updateDepartmentList() is being called");
        $(document).off("change", "#college").on("change", "#college", function() {
            var selectedCollege = $(this).val(); // Get selected college
            var selectedDepartment = $("#department").val(); // Get selected department (if any)
            console.log("college: " + selectedCollege);
            console.log("department: " + selectedDepartment);

    
            if (selectedCollege) {
                $.ajax({
                    url: "ajax/get_departments.php", // Update with actual path
                    type: "POST",
                    data: { college: selectedCollege, department: selectedDepartment },
                    success: function(response) {
                        $("#department").html(response); // Update the department select options
                    },
                    error: function() {
                        console.error("Error fetching department options.");
                    }
                });
            } else {
                $("#department").html('<option value="">Select a department</option>'); // Reset if no college is selected
            }
        });
    }
    

    

    // -------------------------------
    // -------------------------------
    // -------------------------------
    // Map Hours
    // -------------------------------

    // -------------------------------
    // Open Hours Modal
    // -------------------------------
    $(document).on("click", "#edit_map_hours", function (e) {
        e.preventDefault();
        let degreeId = $(this).data("map-id");
        $.ajax({
            url: "ajax/edit_map_hours.php",
            method: "POST",
            data: { degree_map_id: degreeId },
            dataType: "json",
            success: function (response) {
                if (response.error) {
                    alert("Error: " + response.error);
                    return;
                }
                if (!response.modal) {
                    alert("Error: Missing modal content from server.");
                    return;
                }
                if ($("#editHoursModal").length) {
                    $("#editHoursModal").remove();
                }
                $("body").append(response.modal);
                $("#editHoursModal").dialog({
                    modal: true,
                    width: 1000,
                    open: function () {
                    }
                });
                if (response.error) {
                    alert("Error: " + response.error);
                    return;
                }
                // Attach event listeners
                $("#SaveHoursBtn").one("click", function () {
                    saveHours();
                });
                $("#CancelHoursBtn").one("click", function () {
                    $("#editHoursModal").dialog("close");
                });
            }
        });
    });

    // -------------------------------
    // Save Hours 
    // -------------------------------
    function saveHours() {
        let formData = $("#map_hours_form").serialize();
        $.ajax({
            url: "ajax/save_map_hours.php",
            method: "POST",
            data: formData,
            dataType: "json",
            success: function (response) {
                if (response.success) {
                    $("#editHoursModal").dialog("close");
                    refreshCourseTable();
                } else {
                    alert("Error saving map hours: " + response.message);
                }
            },
            error: function () {
                alert("Failed to save map hours.");
            }
        });
    }


});

