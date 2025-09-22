/**
 * Google Drive Admin React Component
 *
 * @package Wpmudev_Plugin_Test
 */

import React, { useState, useEffect, useCallback, useRef } from 'react';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import './scss/style.scss';

const GoogleDriveAdmin = () => {
	// Authentication & Credentials
	const [credentials, setCredentials] = useState({
		clientId: '',
		clientSecret: '',
	});
	const [isAuthenticated, setIsAuthenticated] = useState(false);
	const [isLoading, setIsLoading] = useState(false);
	const [isCheckingAuth, setIsCheckingAuth] = useState(false);
	const [redirectUri, setRedirectUri] = useState('');
	const [credentialsLoaded, setCredentialsLoaded] = useState(false);
	const [credentialsSaved, setCredentialsSaved] = useState(false);

	// File Management
	const [files, setFiles] = useState([]);
	const [selectedFiles, setSelectedFiles] = useState([]);
	const [folderName, setFolderName] = useState('');
	const [currentFolder, setCurrentFolder] = useState(null);
	const [breadcrumbs, setBreadcrumbs] = useState([]);

	// Pagination
	const [pagination, setPagination] = useState({
		currentPage: 1,
		pageSize: 20,
		totalPages: 1,
		hasNextPage: false,
		hasPreviousPage: false,
		nextPageToken: null,
		totalFiles: 0,
	});

	// UI State - removed viewMode as only list view is needed

	// Upload
	const [uploadProgress, setUploadProgress] = useState({});
	const [isUploading, setIsUploading] = useState(false);
	const [dragActive, setDragActive] = useState(false);
	const fileInputRef = useRef(null);

	// UI State
	const [notification, setNotification] = useState(null);
	const [showUploadModal, setShowUploadModal] = useState(false);
	const [showCreateFolderModal, setShowCreateFolderModal] = useState(false);
	const [showDeleteModal, setShowDeleteModal] = useState(false);
	const [fileToDelete, setFileToDelete] = useState(null);
	const [loadingStates, setLoadingStates] = useState({});

	// Refs
	const uploadAreaRef = useRef(null);

	useEffect(() => {
		const initializeApp = async () => {
			await loadCredentials();
			setRedirectUri(
				window.location.origin +
					'/wp-admin/admin.php?page=wpmudev_plugintest_drive'
			);
		};
		initializeApp();
	}, []);

	// Check auth status after credentials are loaded
	useEffect(() => {
		if (
			credentialsLoaded &&
			credentials.clientId &&
			credentials.clientSecret
		) {
			checkAuthStatus();
		} else if (credentialsLoaded) {
			// If credentials are loaded but empty, set authenticated to false
			setIsAuthenticated(false);
			setIsCheckingAuth(false);
		}
	}, [credentialsLoaded, credentials.clientId, credentials.clientSecret]);

	useEffect(() => {
		if (isAuthenticated) {
			loadFiles();
		}
	}, [
		isAuthenticated,
		pagination.currentPage,
		pagination.pageSize,
		currentFolder,
	]);

	/**
	 * Loads stored credentials from the backend.
	 * @return {Promise<void>}
	 */
	const loadCredentials = async () => {
		try {
			const response = await apiFetch({
				path: '/wpmudev/v1/drive/credentials',
				method: 'GET',
			});

			if (response.success) {
				const creds = {
					clientId: response.data?.clientId || '',
					clientSecret: response.data?.clientSecret || '',
				};
				// Only set credentials if they actually have values
				if (
					creds.clientId.trim() !== '' &&
					creds.clientSecret.trim() !== ''
				) {
					setCredentials(creds);
					setCredentialsSaved(true);
				} else {
					// Reset to empty if no valid credentials
					setCredentials({ clientId: '', clientSecret: '' });
					setCredentialsSaved(false);
				}
			}
		} catch (error) {
			// Error loading credentials - handled silently
		} finally {
			setCredentialsLoaded(true);
		}
	};

	/**
	 * Checks the current authentication status with Google Drive.
	 * @return {Promise<void>}
	 */
	const checkAuthStatus = async () => {
		setIsCheckingAuth(true);
		try {
			const response = await apiFetch({
				path: '/wpmudev/v1/drive/auth-status',
				method: 'GET',
			});

			if (response.success) {
				setIsAuthenticated(response.authenticated);
			}
		} catch (error) {
			// Error checking auth status - set to not authenticated
			setIsAuthenticated(false);
		} finally {
			setIsCheckingAuth(false);
		}
	};

	/**
	 * Loads files from Google Drive with pagination support.
	 * @param {number} page - Page number to load.
	 * @param {string|null} pageToken - Token for pagination.
	 * @return {Promise<void>}
	 */
	const loadFiles = async (
		page = pagination.currentPage,
		pageToken = null
	) => {
		if (!isAuthenticated) return;

		setLoadingStates((prev) => ({ ...prev, files: true }));

		try {
			const params = new URLSearchParams({
				page: page.toString(),
				page_size: pagination.pageSize.toString(),
				folder_id: currentFolder || '',
			});

			if (pageToken) {
				params.append('page_token', pageToken);
			}

			const response = await apiFetch({
				path: `/wpmudev/v1/drive/files?${params.toString()}`,
				method: 'GET',
			});

			if (response.success) {
				setFiles(response.files || []);
				setPagination((prev) => {
					const newPagination = {
						...prev,
						currentPage: response.pagination?.currentPage || page,
						totalPages: response.pagination?.totalPages || 1,
						hasNextPage: response.pagination?.hasNextPage || false,
						hasPreviousPage:
							response.pagination?.hasPreviousPage || false,
						nextPageToken:
							response.pagination?.nextPageToken || null,
						totalFiles: response.pagination?.totalFiles || 0,
					};
					return newPagination;
				});
			} else {
				showNotification(
					'error',
					response.message ||
						__('Failed to load files', 'wpmudev-plugin-test')
				);
			}
		} catch (error) {
			// Error loading files - check if authentication is needed

			// If we get a 401 error, it means the token is invalid
			if (error.code === 'no_access_token' || error.status === 401) {
				showNotification(
					'error',
					__(
						'Session expired. Please authenticate again.',
						'wpmudev-plugin-test'
					)
				);
				// Automatically disconnect and reset authentication state
				disconnect();
			} else {
				showNotification(
					'error',
					__('Error loading files:', 'wpmudev-plugin-test') +
						' ' +
						error.message
				);
			}
		} finally {
			setLoadingStates((prev) => ({ ...prev, files: false }));
		}
	};

	/**
	 * Saves Google Drive credentials to the backend.
	 * @param {Event} e - Form submit event.
	 * @return {Promise<void>}
	 */
	const saveCredentials = async (e) => {
		e.preventDefault();
		setIsLoading(true);

		try {
			const response = await apiFetch({
				path: '/wpmudev/v1/drive/save-credentials',
				method: 'POST',
				data: {
					client_id: credentials.clientId,
					client_secret: credentials.clientSecret,
					_wpnonce: window.wpmudevDriveTest?.nonce || '',
				},
			});

			if (response.success) {
				setCredentialsSaved(true);
				showNotification(
					'success',
					__('Credentials saved successfully!', 'wpmudev-plugin-test')
				);
				checkAuthStatus();
			} else {
				showNotification(
					'error',
					response.message ||
						__('Failed to save credentials', 'wpmudev-plugin-test')
				);
			}
		} catch (error) {
			showNotification(
				'error',
				__('Error saving credentials:', 'wpmudev-plugin-test') +
					' ' +
					error.message
			);
		} finally {
			setIsLoading(false);
		}
	};

	/**
	 * Initiates Google Drive authentication flow.
	 * @return {Promise<void>}
	 */
	const authenticate = async () => {
		setIsLoading(true);

		try {
			const response = await apiFetch({
				path: '/wpmudev/v1/drive/authenticate',
				method: 'POST',
			});

			if (response.success && response.auth_url) {
				// Open authentication window
				const authWindow = window.open(
					response.auth_url,
					'google-auth',
					'width=500,height=600,scrollbars=yes,resizable=yes'
				);

				// Check if window is closed
				const checkClosed = setInterval(() => {
					if (authWindow.closed) {
						clearInterval(checkClosed);
						checkAuthStatus();
						loadFiles();
					}
				}, 1000);
			} else {
				showNotification(
					'error',
					response.message ||
						__(
							'Failed to initiate authentication',
							'wpmudev-plugin-test'
						)
				);
			}
		} catch (error) {
			showNotification(
				'error',
				__('Error authenticating:', 'wpmudev-plugin-test') +
					' ' +
					error.message
			);
		} finally {
			setIsLoading(false);
		}
	};

	const disconnect = async () => {
		setIsLoading(true);

		try {
			const response = await apiFetch({
				path: '/wpmudev/v1/drive/disconnect',
				method: 'POST',
			});

			if (response.success) {
				setIsAuthenticated(false);
				setFiles([]);
				setPagination({
					currentPage: 1,
					totalPages: 1,
					hasNextPage: false,
					hasPreviousPage: false,
					nextPageToken: null,
					totalFiles: 0,
				});
				showNotification(
					'success',
					__('Disconnected from Google Drive', 'wpmudev-plugin-test')
				);
			} else {
				showNotification(
					'error',
					response.message ||
						__('Failed to disconnect', 'wpmudev-plugin-test')
				);
			}
		} catch (error) {
			showNotification(
				'error',
				__('Error disconnecting:', 'wpmudev-plugin-test') +
					' ' +
					error.message
			);
		} finally {
			setIsLoading(false);
		}
	};

	const reconfigureApp = async () => {
		setIsLoading(true);

		try {
			// Clear stored credentials
			const response = await apiFetch({
				path: '/wpmudev/v1/drive/clear-credentials',
				method: 'POST',
				data: {
					_wpnonce: window.wpmudevDriveTest?.nonce || '',
				},
			});

			if (response.success) {
				// Reset all states
				setCredentials({ clientId: '', clientSecret: '' });
				setCredentialsSaved(false);
				setIsAuthenticated(false);
				setFiles([]);
				setPagination({
					currentPage: 1,
					totalPages: 1,
					hasNextPage: false,
					hasPreviousPage: false,
					nextPageToken: null,
					totalFiles: 0,
				});
				showNotification(
					'success',
					__(
						'App configuration cleared. You can now reconfigure your app.',
						'wpmudev-plugin-test'
					)
				);
			} else {
				showNotification(
					'error',
					response.message ||
						__(
							'Failed to clear configuration',
							'wpmudev-plugin-test'
						)
				);
			}
		} catch (error) {
			showNotification(
				'error',
				__('Error clearing configuration:', 'wpmudev-plugin-test') +
					' ' +
					error.message
			);
		} finally {
			setIsLoading(false);
		}
	};

	const handleFileSelect = (e) => {
		const files = Array.from(e.target.files);
		if (files.length > 0) {
			uploadFiles(files);
		}
	};

	const handleDragEnter = (e) => {
		e.preventDefault();
		e.stopPropagation();
		setDragActive(true);
	};

	const handleDragLeave = (e) => {
		e.preventDefault();
		e.stopPropagation();
		setDragActive(false);
	};

	const handleDragOver = (e) => {
		e.preventDefault();
		e.stopPropagation();
	};

	const handleDrop = (e) => {
		e.preventDefault();
		e.stopPropagation();
		setDragActive(false);

		const files = Array.from(e.dataTransfer.files);
		if (files.length > 0) {
			uploadFiles(files);
		}
	};

	const uploadFiles = async (files) => {
		setIsUploading(true);
		setUploadProgress({});

		try {
			for (let i = 0; i < files.length; i++) {
				const file = files[i];
				const formData = new FormData();
				formData.append('file', file);

				// Track upload progress for this file
				setUploadProgress((prev) => ({
					...prev,
					[file.name]: { progress: 0, status: 'uploading' },
				}));

				const xhr = new XMLHttpRequest();

				xhr.upload.addEventListener('progress', (e) => {
					if (e.lengthComputable) {
						const progress = Math.round((e.loaded / e.total) * 100);
						setUploadProgress((prev) => ({
							...prev,
							[file.name]: { progress, status: 'uploading' },
						}));
					}
				});

				xhr.addEventListener('load', () => {
					if (xhr.status >= 200 && xhr.status < 300) {
						const response = JSON.parse(xhr.responseText);
						if (response.success) {
							setUploadProgress((prev) => ({
								...prev,
								[file.name]: {
									progress: 100,
									status: 'completed',
								},
							}));
							checkUploadsComplete();
						} else {
							setUploadProgress((prev) => ({
								...prev,
								[file.name]: { progress: 0, status: 'error' },
							}));
							checkUploadsComplete();
						}
					} else if (xhr.status === 401) {
						// Handle 401 error - session expired
						setUploadProgress((prev) => ({
							...prev,
							[file.name]: { progress: 0, status: 'error' },
						}));
						showNotification(
							'error',
							__(
								'Session expired. Please authenticate again.',
								'wpmudev-plugin-test'
							)
						);
						disconnect();
						checkUploadsComplete();
					}
				});

				xhr.addEventListener('error', () => {
					setUploadProgress((prev) => ({
						...prev,
						[file.name]: { progress: 0, status: 'error' },
					}));
					checkUploadsComplete();
				});

				xhr.open('POST', '/wp-json/wpmudev/v1/drive/upload');
				xhr.setRequestHeader(
					'X-WP-Nonce',
					window.wpmudevDriveTest?.nonce || ''
				);
				xhr.send(formData);
			}

			// Track upload completion
			let completedUploads = 0;
			const totalUploads = files.length;

			// Check if all uploads are completed
			const checkUploadsComplete = () => {
				completedUploads++;
				if (completedUploads === totalUploads) {
					loadFiles();
					setUploadProgress({});
					showNotification(
						'success',
						__(
							'Files uploaded successfully!',
							'wpmudev-plugin-test'
						)
					);
				}
			};
		} catch (error) {
			showNotification(
				'error',
				__('Error uploading files:', 'wpmudev-plugin-test') +
					' ' +
					error.message
			);
		} finally {
			setIsUploading(false);
		}
	};

	const createFolder = async (e) => {
		e.preventDefault();
		if (!folderName.trim()) return;

		setIsLoading(true);

		try {
			const response = await apiFetch({
				path: '/wpmudev/v1/drive/create-folder',
				method: 'POST',
				data: {
					name: folderName.trim(),
					parent_id: currentFolder,
				},
			});

			if (response.success) {
				showNotification(
					'success',
					__('Folder created successfully!', 'wpmudev-plugin-test')
				);
				setFolderName('');
				setShowCreateFolderModal(false);
				loadFiles();
			} else {
				showNotification(
					'error',
					response.message ||
						__('Failed to create folder', 'wpmudev-plugin-test')
				);
			}
		} catch (error) {
			showNotification(
				'error',
				__('Error creating folder:', 'wpmudev-plugin-test') +
					' ' +
					error.message
			);
		} finally {
			setIsLoading(false);
		}
	};

	const deleteFiles = async () => {
		if (selectedFiles.length === 0) return;

		setIsLoading(true);

		try {
			const response = await apiFetch({
				path: '/wpmudev/v1/drive/delete-files',
				method: 'POST',
				data: {
					file_ids: selectedFiles,
				},
			});

			if (response.success) {
				showNotification(
					'success',
					__('Files deleted successfully!', 'wpmudev-plugin-test')
				);
				setSelectedFiles([]);
				setShowDeleteModal(false);
				loadFiles();
			} else {
				showNotification(
					'error',
					response.message ||
						__('Failed to delete files', 'wpmudev-plugin-test')
				);
			}
		} catch (error) {
			showNotification(
				'error',
				__('Error deleting files:', 'wpmudev-plugin-test') +
					' ' +
					error.message
			);
		} finally {
			setIsLoading(false);
		}
	};

	const showNotification = (type, message) => {
		setNotification({ type, message });
		setTimeout(() => setNotification(null), 5000);
	};

	const formatFileSize = (bytes) => {
		if (!bytes) return 'Unknown size';
		const sizes = ['Bytes', 'KB', 'MB', 'GB'];
		const i = Math.floor(Math.log(bytes) / Math.log(1024));
		return (
			Math.round((bytes / Math.pow(1024, i)) * 100) / 100 + ' ' + sizes[i]
		);
	};

	const formatDate = (dateString) => {
		const date = new Date(dateString);
		return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
	};

	const toggleFileSelection = (fileId) => {
		setSelectedFiles((prev) =>
			prev.includes(fileId)
				? prev.filter((id) => id !== fileId)
				: [...prev, fileId]
		);
	};

	const navigateToFolder = (folderId) => {
		setCurrentFolder(folderId);
		setPagination((prev) => ({ ...prev, currentPage: 1 }));
		loadFiles(1);
	};

	const getFileIcon = (mimeType) => {
		if (mimeType.includes('folder')) return '📁';
		if (mimeType.includes('image')) return '🖼️';
		if (mimeType.includes('video')) return '🎥';
		if (mimeType.includes('audio')) return '🎵';
		if (mimeType.includes('pdf')) return '📄';
		if (mimeType.includes('document')) return '📝';
		if (mimeType.includes('spreadsheet')) return '📊';
		if (mimeType.includes('presentation')) return '📽️';
		return '📄';
	};

	return (
		<div className="google-drive-admin">
			{/* Notification */}
			{notification && (
				<div
					className={`notice notice-${notification.type} is-dismissible`}
				>
					<p>{notification.message}</p>
					<button
						type="button"
						className="notice-dismiss"
						onClick={() => setNotification(null)}
					>
						<span className="screen-reader-text">
							Dismiss this notice.
						</span>
					</button>
				</div>
			)}

			{/* Loading state while credentials are being loaded */}
			{!credentialsLoaded && (
				<div className="google-drive-section">
					<div className="google-drive-section-body">
						<div className="loading-spinner">
							<div className="spinner"></div>
							<p>{__('Loading...', 'wpmudev-plugin-test')}</p>
						</div>
					</div>
				</div>
			)}

			{/* Credentials Management - Only show if credentials are loaded and not saved */}
			{credentialsLoaded && !credentialsSaved && (
				<div className="google-drive-section">
					<div className="google-drive-section-header">
						<h2>
							{__(
								'Google Drive Credentials',
								'wpmudev-plugin-test'
							)}
						</h2>
					</div>
					<div className="google-drive-section-body">
						<form
							onSubmit={saveCredentials}
							className="credentials-form"
						>
							<div className="credential-field">
								<label htmlFor="client-id">
									{__('Client ID', 'wpmudev-plugin-test')}
								</label>
								<input
									type="text"
									id="client-id"
									value={credentials.clientId}
									onChange={(e) =>
										setCredentials({
											...credentials,
											clientId: e.target.value,
										})
									}
									placeholder={__(
										'Enter Client ID',
										'wpmudev-plugin-test'
									)}
									required
								/>
							</div>
							<div className="credential-field">
								<label htmlFor="client-secret">
									{__('Client Secret', 'wpmudev-plugin-test')}
								</label>
								<input
									type="password"
									id="client-secret"
									value={credentials.clientSecret}
									onChange={(e) =>
										setCredentials({
											...credentials,
											clientSecret: e.target.value,
										})
									}
									placeholder={__(
										'Enter Client Secret',
										'wpmudev-plugin-test'
									)}
									required
								/>
							</div>
							<div className="credential-field">
								<button
									type="submit"
									className="button-primary"
									disabled={isLoading}
								>
									{isLoading
										? __('Saving...', 'wpmudev-plugin-test')
										: __(
												'Save Credentials',
												'wpmudev-plugin-test'
											)}
								</button>
							</div>
						</form>

						<div className="redirect-uri-info">
							<h4>
								{__(
									'Redirect URI Configuration',
									'wpmudev-plugin-test'
								)}
							</h4>
							<p>
								{__(
									'Add this exact URL to your Google Cloud Console OAuth 2.0 Client configuration:',
									'wpmudev-plugin-test'
								)}
							</p>
							<div className="redirect-uri-code">
								{redirectUri}
							</div>
						</div>

						<div className="oauth-scopes">
							<h4>
								{__(
									'Required OAuth Scopes',
									'wpmudev-plugin-test'
								)}
							</h4>
							<p>
								{__(
									'This plugin requires the following Google Drive API permissions:',
									'wpmudev-plugin-test'
								)}
							</p>
							<ul className="scope-list">
								<li>
									<strong>
										https://www.googleapis.com/auth/drive.file
									</strong>{' '}
									-{' '}
									{__(
										'Drive File Access',
										'wpmudev-plugin-test'
									)}
								</li>
								<li>
									<strong>
										https://www.googleapis.com/auth/drive.readonly
									</strong>{' '}
									-{' '}
									{__(
										'Drive Read-Only Access',
										'wpmudev-plugin-test'
									)}
								</li>
							</ul>
						</div>
					</div>
				</div>
			)}

			{/* Authentication Status - Only show if credentials are loaded and saved */}
			{credentialsLoaded && credentialsSaved && (
				<div className="google-drive-section">
					<div className="google-drive-section-header">
						<h2>
							{__('Authentication Status', 'wpmudev-plugin-test')}
						</h2>
					</div>
					<div className="google-drive-section-body">
						<div
							className={`auth-status ${isCheckingAuth ? 'checking' : isAuthenticated ? 'authenticated' : 'not-authenticated'}`}
						>
							<span className="auth-status-icon">
								{isCheckingAuth
									? '⏳'
									: isAuthenticated
										? '✅'
										: '❌'}
							</span>
							{isCheckingAuth
								? __(
										'Checking connection...',
										'wpmudev-plugin-test'
									)
								: isAuthenticated
									? __(
											'Connected to Google Drive',
											'wpmudev-plugin-test'
										)
									: __(
											'Not connected to Google Drive',
											'wpmudev-plugin-test'
										)}
						</div>

						<div className="auth-actions">
							{!isAuthenticated ? (
								<button
									onClick={authenticate}
									className="button-primary"
									disabled={isLoading || isCheckingAuth}
								>
									{isLoading
										? __(
												'Authenticating...',
												'wpmudev-plugin-test'
											)
										: __(
												'Authenticate with Google Drive',
												'wpmudev-plugin-test'
											)}
								</button>
							) : (
								<button
									onClick={disconnect}
									className="button-secondary"
									disabled={isLoading || isCheckingAuth}
								>
									{isLoading
										? __(
												'Disconnecting...',
												'wpmudev-plugin-test'
											)
										: __(
												'Disconnect',
												'wpmudev-plugin-test'
											)}
								</button>
							)}

							<button
								onClick={reconfigureApp}
								className="button-secondary"
								disabled={isLoading || isCheckingAuth}
							>
								{__('Reconfigure App', 'wpmudev-plugin-test')}
							</button>
						</div>
					</div>
				</div>
			)}

			{/* File Operations Interface */}
			{isAuthenticated && (
				<>
					{/* File Management Toolbar */}
					<div className="google-drive-section">
						<div className="google-drive-section-header">
							<h2>
								{__('File Management', 'wpmudev-plugin-test')}
							</h2>
						</div>
						<div className="google-drive-section-body">
							<div className="file-toolbar">
								<div className="toolbar-left">
									<button
										onClick={() => setShowUploadModal(true)}
										className="button-primary"
									>
										{__(
											'Upload Files',
											'wpmudev-plugin-test'
										)}
									</button>
									<button
										onClick={() =>
											setShowCreateFolderModal(true)
										}
										className="button-secondary"
									>
										{__(
											'Create Folder',
											'wpmudev-plugin-test'
										)}
									</button>
									{selectedFiles.length > 0 && (
										<button
											onClick={() =>
												setShowDeleteModal(true)
											}
											className="button-danger"
										>
											{__(
												'Delete Selected',
												'wpmudev-plugin-test'
											)}{' '}
											({selectedFiles.length})
										</button>
									)}
								</div>

								<div className="toolbar-right">
									<select
										value={pagination.pageSize}
										onChange={(e) => {
											setPagination((prev) => ({
												...prev,
												pageSize: parseInt(
													e.target.value
												),
												currentPage: 1,
											}));
										}}
										className="sort-select"
									>
										<option value="5">
											{__(
												'5 per page',
												'wpmudev-plugin-test'
											)}
										</option>
										<option value="10">
											{__(
												'10 per page',
												'wpmudev-plugin-test'
											)}
										</option>
										<option value="20">
											{__(
												'20 per page',
												'wpmudev-plugin-test'
											)}
										</option>
										<option value="50">
											{__(
												'50 per page',
												'wpmudev-plugin-test'
											)}
										</option>
										<option value="100">
											{__(
												'100 per page',
												'wpmudev-plugin-test'
											)}
										</option>
									</select>
								</div>
							</div>
						</div>
					</div>

					{/* Breadcrumbs */}
					{breadcrumbs.length > 0 && (
						<div className="google-drive-section">
							<div className="breadcrumbs">
								<button onClick={() => navigateToFolder(null)}>
									{__('Drive Root', 'wpmudev-plugin-test')}
								</button>
								{breadcrumbs.map((crumb, index) => (
									<React.Fragment key={crumb.id}>
										<span className="breadcrumb-separator">
											/
										</span>
										<button
											onClick={() =>
												navigateToFolder(crumb.id)
											}
										>
											{crumb.name}
										</button>
									</React.Fragment>
								))}
							</div>
						</div>
					)}

					{/* Files List */}
					<div className="google-drive-section">
						<div className="google-drive-section-header">
							<h2>
								{__('Your Drive Files', 'wpmudev-plugin-test')}
							</h2>

							{/* Pagination Controls - Show when there are files */}
							{files.length > 0 && (
								<div className="pagination-controls">
									<button
										onClick={() =>
											loadFiles(
												pagination.currentPage - 1
											)
										}
										disabled={
											pagination.currentPage <= 1 ||
											loadingStates.files
										}
										className="pagination-btn"
									>
										{__('Previous', 'wpmudev-plugin-test')}
									</button>

									<div className="pagination-info">
										{__('Page', 'wpmudev-plugin-test')}{' '}
										{pagination.currentPage}
									</div>

									<button
										onClick={() =>
											loadFiles(
												pagination.currentPage + 1
											)
										}
										disabled={loadingStates.files}
										className="pagination-btn"
									>
										{__('Next', 'wpmudev-plugin-test')}
									</button>
								</div>
							)}
						</div>
						<div className="google-drive-section-body">
							{loadingStates.files ? (
								<div className="loading-spinner">
									<div className="spinner"></div>
									<p>
										{__(
											'Loading files...',
											'wpmudev-plugin-test'
										)}
									</p>
								</div>
							) : (
								<div className="files-container list">
									{files.length === 0 ? (
										<div className="no-files">
											{__(
												'No files found in your Drive. Upload a file or create a folder to get started.',
												'wpmudev-plugin-test'
											)}
										</div>
									) : (
										files.map((file, index) => (
											<div
												key={file.id || index}
												className={`file-item ${selectedFiles.includes(file.id) ? 'selected' : ''}`}
											>
												<div className="file-checkbox">
													<input
														type="checkbox"
														checked={selectedFiles.includes(
															file.id
														)}
														onChange={() =>
															toggleFileSelection(
																file.id
															)
														}
													/>
												</div>
												<div
													className="file-icon"
													onClick={() =>
														file.mimeType ===
														'application/vnd.google-apps.folder'
															? navigateToFolder(
																	file.id
																)
															: null
													}
												>
													{getFileIcon(file.mimeType)}
												</div>
												<div className="file-info">
													<div
														className="file-name"
														onClick={() =>
															file.mimeType ===
															'application/vnd.google-apps.folder'
																? navigateToFolder(
																		file.id
																	)
																: null
														}
													>
														{file.name}
													</div>
													<div className="file-meta">
														{file.mimeType !==
															'application/vnd.google-apps.folder' &&
															formatFileSize(
																file.size
															)}{' '}
														•{' '}
														{formatDate(
															file.modifiedTime
														)}
													</div>
												</div>
												<div className="file-actions">
													{file.mimeType !==
													'application/vnd.google-apps.folder' ? (
														<>
															<a
																href={
																	file.webViewLink
																}
																target="_blank"
																rel="noopener noreferrer"
																className="file-action-btn"
															>
																{__(
																	'View in Drive',
																	'wpmudev-plugin-test'
																)}
															</a>
															<a
																href={
																	file.webContentLink
																}
																download
																className="file-action-btn"
															>
																{__(
																	'Download',
																	'wpmudev-plugin-test'
																)}
															</a>
														</>
													) : (
														<a
															href={
																file.webViewLink
															}
															target="_blank"
															rel="noopener noreferrer"
															className="file-action-btn"
														>
															{__(
																'View in Drive',
																'wpmudev-plugin-test'
															)}
														</a>
													)}
												</div>
											</div>
										))
									)}
								</div>
							)}
						</div>
					</div>
				</>
			)}

			{/* Upload Modal */}
			{showUploadModal && (
				<div className="modal-overlay">
					<div className="modal-content">
						<div className="modal-header">
							<h3>{__('Upload Files', 'wpmudev-plugin-test')}</h3>
							<button
								onClick={() => setShowUploadModal(false)}
								className="modal-close"
							>
								×
							</button>
						</div>
						<div className="modal-body">
							<div
								className={`upload-area ${dragActive ? 'drag-active' : ''}`}
								onDragEnter={handleDragEnter}
								onDragLeave={handleDragLeave}
								onDragOver={handleDragOver}
								onDrop={handleDrop}
								ref={uploadAreaRef}
							>
								<div className="upload-icon">📁</div>
								<div className="upload-text">
									{__(
										'Drag and drop files here or click to select',
										'wpmudev-plugin-test'
									)}
								</div>
								<input
									type="file"
									ref={fileInputRef}
									onChange={handleFileSelect}
									multiple
									style={{ display: 'none' }}
								/>
								<button
									onClick={() =>
										fileInputRef.current?.click()
									}
									className="button-primary"
								>
									{__('Select Files', 'wpmudev-plugin-test')}
								</button>
							</div>

							{/* Upload Progress */}
							{Object.keys(uploadProgress).length > 0 && (
								<div className="upload-progress-list">
									{Object.entries(uploadProgress).map(
										([fileName, progress]) => (
											<div
												key={fileName}
												className="upload-progress-item"
											>
												<div className="progress-file-name">
													{fileName}
												</div>
												<div className="progress-bar">
													<div
														className="progress-fill"
														style={{
															width: `${progress.progress}%`,
														}}
													/>
												</div>
												<div className="progress-status">
													{progress.status ===
													'completed'
														? '✅'
														: progress.status ===
															  'error'
															? '❌'
															: `${progress.progress}%`}
												</div>
											</div>
										)
									)}
								</div>
							)}
						</div>
					</div>
				</div>
			)}

			{/* Create Folder Modal */}
			{showCreateFolderModal && (
				<div className="modal-overlay">
					<div className="modal-content">
						<div className="modal-header">
							<h3>
								{__('Create New Folder', 'wpmudev-plugin-test')}
							</h3>
							<button
								onClick={() => setShowCreateFolderModal(false)}
								className="modal-close"
							>
								×
							</button>
						</div>
						<div className="modal-body">
							<form onSubmit={createFolder}>
								<div className="form-field">
									<label htmlFor="folder-name">
										{__(
											'Folder Name',
											'wpmudev-plugin-test'
										)}
									</label>
									<input
										type="text"
										id="folder-name"
										value={folderName}
										onChange={(e) =>
											setFolderName(e.target.value)
										}
										placeholder={__(
											'Enter folder name',
											'wpmudev-plugin-test'
										)}
										required
										autoFocus
									/>
								</div>
								<div className="form-actions">
									<button
										type="button"
										onClick={() =>
											setShowCreateFolderModal(false)
										}
										className="button-secondary"
									>
										{__('Cancel', 'wpmudev-plugin-test')}
									</button>
									<button
										type="submit"
										className="button-primary"
										disabled={
											isLoading || !folderName.trim()
										}
									>
										{isLoading
											? __(
													'Creating...',
													'wpmudev-plugin-test'
												)
											: __(
													'Create Folder',
													'wpmudev-plugin-test'
												)}
									</button>
								</div>
							</form>
						</div>
					</div>
				</div>
			)}

			{/* Delete Confirmation Modal */}
			{showDeleteModal && (
				<div className="modal-overlay">
					<div className="modal-content">
						<div className="modal-header">
							<h3>{__('Delete Files', 'wpmudev-plugin-test')}</h3>
							<button
								onClick={() => setShowDeleteModal(false)}
								className="modal-close"
							>
								×
							</button>
						</div>
						<div className="modal-body">
							<p>
								{__(
									'Are you sure you want to delete the selected files? This action cannot be undone.',
									'wpmudev-plugin-test'
								)}
							</p>
							<div className="form-actions">
								<button
									type="button"
									onClick={() => setShowDeleteModal(false)}
									className="button-secondary"
								>
									{__('Cancel', 'wpmudev-plugin-test')}
								</button>
								<button
									onClick={deleteFiles}
									className="button-danger"
									disabled={isLoading}
								>
									{isLoading
										? __(
												'Deleting...',
												'wpmudev-plugin-test'
											)
										: __(
												'Delete Files',
												'wpmudev-plugin-test'
											)}
								</button>
							</div>
						</div>
					</div>
				</div>
			)}
		</div>
	);
};

export default GoogleDriveAdmin;

// Mount the React component
if (typeof window !== 'undefined' && window.wpmudevDriveTest) {
	const { dom_element_id } = window.wpmudevDriveTest;
	const element = document.getElementById(dom_element_id);
	if (element) {
		const { createElement, render } = window.wp.element;
		render(createElement(GoogleDriveAdmin), element);
	}
}
