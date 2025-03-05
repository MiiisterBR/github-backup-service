$(document).ready(function () {
    // Salt for encryption (must match the ENCRYPTION_SALT in config.php)
    const encryptionSalt = "16N!z<X0*85-NuPq";

    // Global variable for the current AJAX request
    let currentRequest = null;

    // Hide the global cancel backup process button by default
    $("#cancelBackupProcessBtn").hide();

    // Global variables for sequential backup process control
    window.backupAllCancelled = false;
    window.isBackupAllInProgress = false;

    // Global AJAX handlers to show/hide loading indicators and cancel button
    $(document).ajaxStart(() => {
        $("#loading").removeClass("hidden");
        $("#cancelLoadingBtn").show();
    });
    $(document).ajaxStop(() => {
        $("#loading").addClass("hidden");
        $("#cancelLoadingBtn").hide();
    });

    // Click handler for Cancel Loading button: abort the current AJAX request if it exists
    $("#cancelLoadingBtn").click((e) => {
        e.preventDefault();
        if (currentRequest) {
            currentRequest.abort();
            currentRequest = null;
            showToast("AJAX request cancelled.", "warning");
        }
        $("#loading").addClass("hidden");
        $("#cancelLoadingBtn").hide();
    });

    // ----------------------------
    // Cookie Helper Functions
    // ----------------------------
    function setCookie(name, value, days) {
        let expires = "";
        if (days) {
            const date = new Date();
            date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000);
            expires = "; expires=" + date.toUTCString();
        }
        document.cookie = name + "=" + (value || "") + expires + "; path=/";
    }

    function getCookie(name) {
        const nameEQ = name + "=";
        const ca = document.cookie.split(';');
        for (let i = 0; i < ca.length; i++) {
            let c = ca[i];
            while (c.charAt(0) === ' ') c = c.substring(1);
            if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length);
        }
        return null;
    }

    function eraseCookie(name) {
        document.cookie = name + '=; Max-Age=-99999999;';
    }

    // ----------------------------
    // Setup IndexedDB to store repository status
    // (including last backup date and ignored status)
    // ----------------------------
    let db;
    const openRequest = indexedDB.open("backupDB", 2);

    openRequest.onerror = function (event) {
        console.error("IndexedDB error:", event);
    };

    openRequest.onupgradeneeded = function (event) {
        db = event.target.result;
        if (!db.objectStoreNames.contains("repoStatus")) {
            db.createObjectStore("repoStatus", { keyPath: "repoName" });
        }
    };

    openRequest.onsuccess = function (event) {
        db = event.target.result;
    };

    // ----------------------------
    // Simple encryption functions (base64 + salt)
    // ----------------------------
    function encryptData(data) {
        return btoa(encryptionSalt + data);
    }

    function decryptData(encryptedData) {
        const decoded = atob(encryptedData);
        return decoded.replace(encryptionSalt, "");
    }

    // ----------------------------
    // Toast Notification Function using Tailwind CSS
    // ----------------------------
    function showToast(message, type = "info") {
        if (!$('#toast-container').length) {
            $('body').append('<div id="toast-container" class="fixed top-5 right-5 space-y-2 z-50"></div>');
        }
        let bgClass;
        switch (type) {
            case "success":
                bgClass = "bg-green-500";
                break;
            case "error":
                bgClass = "bg-red-500";
                break;
            case "warning":
                bgClass = "bg-yellow-500";
                break;
            default:
                bgClass = "bg-blue-500";
        }
        const toast = $(`
            <div class="max-w-xs w-full ${bgClass} text-white p-4 rounded shadow flex justify-between items-center">
                <span>${message}</span>
                <button class="toast-close text-xl leading-none">&times;</button>
            </div>
        `);
        $("#toast-container").append(toast);
        toast.find('.toast-close').click(() => {
            toast.fadeOut(300, () => toast.remove());
        });
        setTimeout(() => {
            toast.fadeOut(300, () => toast.remove());
        }, 5000);
    }

    // ----------------------------
    // Apply data from IndexedDB to the DOM after repositories are rendered
    // ----------------------------
    function applyIndexedDBStatus() {
        if (!db) return;
        const transaction = db.transaction(["repoStatus"], "readonly");
        const store = transaction.objectStore("repoStatus");
        const getAllRequest = store.getAll();
        getAllRequest.onsuccess = function (event) {
            const statuses = event.target.result;
            statuses.forEach(function (item) {
                // Match using the data-repo-name attribute
                const repoBlock = $(`[data-repo-name="${item.repoName}"]`);
                if (repoBlock.length) {
                    if (item.lastBackup) {
                        repoBlock.find(".lastBackup").text("Last Backup: " + new Date(item.lastBackup).toLocaleString());
                    }
                    if (item.ignored) {
                        const statusEl = repoBlock.find(".status");
                        statusEl.text("Ignored").removeClass("bg-blue-500 bg-green-500").addClass("bg-gray-500");
                        repoBlock.find(".backupBtn").prop("disabled", true);
                    }
                }
            });
        };
    }

    // ----------------------------
    // User Interaction Handlers
    // ----------------------------

    // Fetch repositories when "Fetch Repositories" is clicked
    $("#fetchReposBtn").click(fetchRepos);

    // Handle user login: save credentials in cookies and display dashboard
    $("#userForm").submit(function (e) {
        e.preventDefault();
        const username = $("#username").val();
        const token = $("#token").val();
        const encryptedUsername = encryptData(username);
        const encryptedToken = encryptData(token);
        setCookie("encrypted_username", encryptedUsername, 3650);
        setCookie("encrypted_token", encryptedToken, 3650);
        $("#userForm").parent().hide();
        $("#dashboard").show();
    });

    // Auto-login: if credentials are stored, show dashboard and hide login form
    if (getCookie("encrypted_username") && getCookie("encrypted_token")) {
        $("#userForm").parent().hide();
        $("#dashboard").show();
    }

    // Clear stored data and reload page
    $("#clearStorage").click(function (e) {
        e.preventDefault();
        eraseCookie("encrypted_username");
        eraseCookie("encrypted_token");
        if (db) {
            const transaction = db.transaction(["repoStatus"], "readwrite");
            const store = transaction.objectStore("repoStatus");
            const clearRequest = store.clear();
            clearRequest.onsuccess = function () {
                showToast("Local data cleared.", "success");
                $("#userForm")[0].reset();
                window.location.reload();
            };
            clearRequest.onerror = function () {
                showToast("Error clearing local data.", "error");
                $("#loading").addClass("hidden");
            };
        } else {
            window.location.reload();
        }
    });

    // ----------------------------
    // Fetch Repositories Function
    // ----------------------------
    function fetchRepos() {
        const encryptedToken = getCookie("encrypted_token");
        if (!encryptedToken) {
            showToast("Token not found.", "error");
            return;
        }
        const fetchCollaborators = $("#fetchCollaboratorsCheckbox").is(":checked") ? "true" : "false";
        const useOrg = $("#orgCheckbox").is(":checked") ? "true" : "false";
        const organizationName = $("#orgNameInput").val();
        let postData = {
            encrypted_token: encryptedToken,
            fetch_collaborators: fetchCollaborators,
            org: useOrg
        };
        // For organization mode, pass organization name; otherwise, pass username
        if (useOrg === "true") {
            postData.organization = organizationName;
        } else {
            postData.username = $("#username").val();
        }
        currentRequest = $.ajax({
            url: "../api/get_repos.php",
            type: "POST",
            data: postData,
            dataType: "json",
            success: function (response) {
                currentRequest = null;
                if (response.status === "success") {
                    renderRepos(response.repos);
                } else {
                    showToast(response.message, "error");
                }
            },
            error: function () {
                currentRequest = null;
                showToast("Error fetching repositories.", "error");
            }
        });
    }

    // ----------------------------
    // Render Repositories Function
    // ----------------------------
    function renderRepos(repos) {
        $("#repoList").empty();
        $("#repoCount").html(`<span class="px-2 py-1 bg-indigo-600 text-white rounded">${repos.length} Total Repositories</span>`);
        $("#backupCount").html(`<span class="px-2 py-1 bg-green-600 text-white rounded">0 Repositories Backed Up</span>`);

        repos.forEach(function (repo) {
            let collabHTML = "";
            if (repo.collaborators && repo.collaborators.length > 0) {
                collabHTML = '<ul class="mt-1 text-xs text-gray-700">';
                repo.collaborators.forEach(function (collab) {
                    // Determine access level based on permissions
                    let accessLevel = "N/A";
                    if (collab.permissions) {
                        if (collab.permissions.admin) {
                            accessLevel = "admin";
                        } else if (collab.permissions.maintain) {
                            accessLevel = "maintain";
                        } else if (collab.permissions.push) {
                            accessLevel = "write";
                        } else if (collab.permissions.triage) {
                            accessLevel = "triage";
                        } else if (collab.permissions.pull) {
                            accessLevel = "read";
                        }
                    }
                    collabHTML += `<li>${collab.login} (${accessLevel})</li>`;
                });
                collabHTML += '</ul>';
            } else {
                collabHTML = '<p class="mt-1 text-xs text-gray-500">No collaborators found</p>';
            }

            const repoBlock = $(`
                <div class="p-4 border rounded flex flex-col md:flex-row md:justify-between md:items-center"
                     data-repo='${JSON.stringify(repo)}'
                     data-repo-name="${repo.name}">
                    <div>
                        <h3 class="font-bold text-lg">${repo.name}</h3>
                        <div class="flex items-center space-x-2 mt-1">
                            <span class="status inline-block px-2 py-1 text-xs font-semibold text-white bg-blue-500 rounded">
                                Pending
                            </span>
                            <span class="lastBackup text-xs text-gray-600"></span>
                        </div>
                        <div class="collaborators mt-2">
                            <strong class="text-sm">Members:</strong>
                            ${collabHTML}
                        </div>
                    </div>
                    <div class="mt-2 md:mt-0 space-x-2">
                        <button class="backupBtn bg-blue-500 text-white px-2 py-1 rounded text-xs">Backup</button>
                        <button class="ignoreBtn bg-gray-500 text-white px-2 py-1 rounded text-xs">Ignore</button>
                        <button class="cancelBtn bg-red-500 text-white px-2 py-1 rounded text-xs" disabled>Cancel</button>
                    </div>
                </div>
            `);
            $("#repoList").append(repoBlock);
        });

        // Apply status data from IndexedDB
        applyIndexedDBStatus();
        // Populate global repository and collaborator select boxes
        populateRepoSelect(repos);
        populateCollaboratorSelect(repos);
    }

    // ----------------------------
    // Collaborator and Repository Select Helpers
    // ----------------------------
    // Get unique collaborators across all repositories
    function getUniqueCollaborators(repos) {
        const uniqueMap = {};
        repos.forEach(repo => {
            if (repo.collaborators && repo.collaborators.length > 0) {
                repo.collaborators.forEach(collab => {
                    if (!uniqueMap[collab.login]) {
                        uniqueMap[collab.login] = collab;
                    }
                });
            }
        });
        return Object.values(uniqueMap);
    }

    // Populate the global collaborator select box
    function populateCollaboratorSelect(repos) {
        const uniqueCollabs = getUniqueCollaborators(repos);
        const select = $("#collaboratorSelect");
        select.empty();
        uniqueCollabs.forEach(collab => {
            let accessLevel = "N/A";
            if (collab.permissions) {
                if (collab.permissions.admin) {
                    accessLevel = "admin";
                } else if (collab.permissions.push) {
                    accessLevel = "write";
                } else if (collab.permissions.pull) {
                    accessLevel = "read";
                }
            }
            select.append(`<option value="${collab.login}">${collab.login}</option>`);
        });
    }

    // Populate the repository select box for collaborator management
    function populateRepoSelect(repos) {
        const select = $("#repoSelect");
        select.empty();
        select.append('<option value="all">All Repositories</option>');
        repos.forEach(repo => {
            select.append(`<option value="${repo.name}">${repo.name}</option>`);
        });
    }

    // ----------------------------
    // Update Collaborator Access Level Handler
    // ----------------------------
    $("#updateAccessBtn").click(function (e) {
        e.preventDefault();
        const selectedRepo = $("#repoSelect").val(); // "all" or specific repository name
        const selectedUser = $("#collaboratorSelect").val();
        const newAccess = $("#accessLevelSelect").val();

        // Determine owner based on org checkbox: if org is used, take value from orgNameInput, else use username input
        const useOrg = $("#orgCheckbox").is(":checked") ? "true" : "false";
        let owner;
        if (useOrg === "true") {
            owner = $("#orgNameInput").val();
        } else {
            owner = $("#username").val();
        }

        const postData = {
            encrypted_token: getCookie("encrypted_token"),
            username: selectedUser,
            access: newAccess,
            owner: owner
        };

        if (selectedRepo === "all") {
            $.ajax({
                url: "../api/update_collaborator.php",
                type: "POST",
                data: postData,
                dataType: "json",
                success: function (response) {
                    if (response.status === "success") {
                        showToast(`Access updated for ${selectedUser} in all repositories`, "success");
                    } else {
                        showToast(response.message, "error");
                    }
                },
                error: function () {
                    showToast(`Error updating access for ${selectedUser}`, "error");
                }
            });
        } else {
            postData.repo_name = selectedRepo;
            $.ajax({
                url: "../api/update_repo_collaborator.php",
                type: "POST",
                data: postData,
                dataType: "json",
                success: function (response) {
                    if (response.status === "success") {
                        showToast(`Access updated for ${selectedUser} in ${selectedRepo}`, "success");
                    } else {
                        showToast(response.message, "error");
                    }
                },
                error: function () {
                    showToast(`Error updating access for ${selectedUser} in ${selectedRepo}`, "error");
                }
            });
        }
    });

    // ----------------------------
    // Remove Collaborator Handler
    // ----------------------------
    $("#removeCollabBtn").click(function (e) {
        e.preventDefault();
        const selectedRepo = $("#repoSelect").val(); // "all" or specific repository name
        const selectedUser = $("#collaboratorSelect").val();

        // Determine owner based on org checkbox
        const useOrg = $("#orgCheckbox").is(":checked") ? "true" : "false";
        let owner;
        if (useOrg === "true") {
            owner = $("#orgNameInput").val();
        } else {
            owner = $("#username").val();
        }

        const postData = {
            encrypted_token: getCookie("encrypted_token"),
            username: selectedUser,
            owner: owner
        };

        if (selectedRepo === "all") {
            $.ajax({
                url: "../api/remove_collaborator.php",
                type: "POST",
                data: postData,
                dataType: "json",
                success: function (response) {
                    if (response.status === "success") {
                        showToast(`${selectedUser} removed from all repositories`, "success");
                    } else {
                        showToast(response.message, "error");
                    }
                },
                error: function () {
                    showToast(`Error removing ${selectedUser}`, "error");
                }
            });
        } else {
            postData.repo_name = selectedRepo;
            $.ajax({
                url: "../api/remove_repo_collaborator.php",
                type: "POST",
                data: postData,
                dataType: "json",
                success: function (response) {
                    if (response.status === "success") {
                        showToast(`${selectedUser} removed from ${selectedRepo}`, "success");
                    } else {
                        showToast(response.message, "error");
                    }
                },
                error: function () {
                    showToast(`Error removing ${selectedUser} from ${selectedRepo}`, "error");
                }
            });
        }
    });

    // ----------------------------
    // Backup a Single Repository
    // ----------------------------
    $(document).on("click", ".backupBtn", function (e) {
        e.preventDefault();
        const repoBlock = $(this).closest("[data-repo]");
        const repo = repoBlock.data("repo");
        // Disable backup and ignore buttons; enable cancel button
        repoBlock.find(".backupBtn, .ignoreBtn").prop("disabled", true);
        repoBlock.find(".cancelBtn").prop("disabled", false);
        // Update status to "Backing up" with a yellow background
        repoBlock.find(".status").text("Backing up").removeClass("bg-blue-500").addClass("bg-yellow-500");

        const encryptedToken = getCookie("encrypted_token");
        currentRequest = $.ajax({
            url: "../api/backup.php",
            type: "POST",
            data: { encrypted_token: encryptedToken, repo: JSON.stringify(repo) },
            dataType: "json",
            success: function (response) {
                currentRequest = null;
                if (response.status === "success") {
                    repoBlock.find(".status").text("Backup completed").removeClass("bg-yellow-500").addClass("bg-green-500");
                    const now = new Date();
                    repoBlock.find(".lastBackup").text("Last Backup: " + now.toLocaleString());
                    showToast(`Backup completed for ${repo.name}`, "success");
                    if (db) {
                        const transaction = db.transaction(["repoStatus"], "readwrite");
                        const store = transaction.objectStore("repoStatus");
                        store.put({ repoName: repo.name, lastBackup: now.toISOString(), ignored: false });
                    }
                    updateBackupCount();
                } else if (response.status === "cancelled") {
                    repoBlock.find(".status").text("Backup cancelled").removeClass("bg-yellow-500").addClass("bg-red-500");
                    showToast(`Backup cancelled for ${repo.name}`, "warning");
                } else {
                    repoBlock.find(".status").text("Error: " + response.message).removeClass("bg-yellow-500").addClass("bg-red-500");
                    showToast(`Error in backup for ${repo.name}: ${response.message}`, "error");
                }
                repoBlock.find(".cancelBtn").prop("disabled", true);
            },
            error: function () {
                currentRequest = null;
                repoBlock.find(".status").text("AJAX error").removeClass("bg-yellow-500").addClass("bg-red-500");
                repoBlock.find(".cancelBtn").prop("disabled", true);
                showToast(`AJAX error for ${repo.name}`, "error");
            }
        });
    });

    // ----------------------------
    // Toggle Ignore State for a Repository
    // ----------------------------
    $(document).on("click", ".ignoreBtn", function (e) {
        e.preventDefault();
        const repoBlock = $(this).closest("[data-repo]");
        const statusEl = repoBlock.find(".status");
        const currentStatus = statusEl.text().trim().toLowerCase();
        const repo = repoBlock.data("repo");

        if (currentStatus === "ignored") {
            // Revert to "Pending": update status and re-enable backup button
            statusEl.text("Pending").removeClass("bg-gray-500").addClass("bg-blue-500");
            repoBlock.find(".backupBtn").prop("disabled", false);
            // Remove ignore flag from IndexedDB
            if (db) {
                const transaction = db.transaction(["repoStatus"], "readwrite");
                const store = transaction.objectStore("repoStatus");
                store.delete(repo.name);
            }
        } else {
            // Set status to "Ignored": update status and disable backup button
            statusEl.text("Ignored").removeClass("bg-blue-500 bg-green-500").addClass("bg-gray-500");
            repoBlock.find(".backupBtn").prop("disabled", true);
            // Update ignore flag in IndexedDB
            if (db) {
                const transaction = db.transaction(["repoStatus"], "readwrite");
                const store = transaction.objectStore("repoStatus");
                store.put({ repoName: repo.name, ignored: true });
            }
        }
    });

    // ----------------------------
    // Cancel Backup for a Repository
    // ----------------------------
    $(document).on("click", ".cancelBtn", function (e) {
        e.preventDefault();
        const repoBlock = $(this).closest("[data-repo]");
        const repo = repoBlock.data("repo");
        $.ajax({
            url: "../api/cancel_backup.php",
            type: "POST",
            data: { repo_name: repo.name },
            dataType: "json",
            success: function () {
                repoBlock.find(".status").text("Cancelling").removeClass("bg-yellow-500").addClass("bg-red-500");
                repoBlock.find(".cancelBtn").prop("disabled", true);
                showToast(`Cancelling backup for ${repo.name}`, "warning");
            },
            error: function () {
                showToast(`Error sending cancel request for ${repo.name}`, "error");
            }
        });
    });

    // ----------------------------
    // "Backup All" Sequential Processing
    // ----------------------------
    $("#backupAllBtn").click(function (e) {
        e.preventDefault();
        window.backupAllCancelled = false;
        window.isBackupAllInProgress = true;
        // Show the global cancel backup process button
        $("#cancelBackupProcessBtn").show();

        // Append spinner if not already present
        if ($("#backupAllSpinner").length === 0) {
            $("#backupAllBtn").append('<span id="backupAllSpinner" class="ml-2 animate-spin border-t-2 border-white rounded-full w-4 h-4 inline-block"></span>');
        }

        // Get repository blocks as an array and process sequentially
        const repoBlocks = $("#repoList > div");
        let currentIndex = 0;
        function backupNext() {
            if (window.backupAllCancelled || currentIndex >= repoBlocks.length) {
                $("#backupAllSpinner").remove();
                $("#cancelBackupProcessBtn").hide();
                window.isBackupAllInProgress = false;
                return;
            }
            const repoBlock = $(repoBlocks[currentIndex]);
            currentIndex++;
            // Trigger backup if repository is not ignored
            if (repoBlock.find(".status").text() !== "Ignored") {
                repoBlock.find(".backupBtn").trigger("click");
            }
            // Call backupNext after a fixed delay (adjust delay as needed)
            setTimeout(backupNext, 3000);
        }
        backupNext();
    });

    // Global "Cancel Backup Process" button click event
    $("#cancelBackupProcessBtn").click(function (e) {
        e.preventDefault();
        window.backupAllCancelled = true;
        showToast("Backup process cancelled.", "warning");
    });

    // ----------------------------
    // Update Backup Count Function
    // ----------------------------
    function updateBackupCount() {
        let backedUpCount = 0;
        $("#repoList > div").each(function () {
            const statusText = $(this).find(".status").text();
            if (statusText === "Backup completed") {
                backedUpCount++;
            }
        });
        $("#backupCount").html(`<span class="px-2 py-1 bg-green-600 text-white rounded">${backedUpCount} Repositories Backed Up</span>`);
    }
});
