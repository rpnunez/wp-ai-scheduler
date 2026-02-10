/**
 * Session Modal Component
 *
 * Displays generation session details including AI calls and logs.
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Modal, Spinner, TabPanel, Notice } from '@wordpress/components';

const SessionModal = ({ sessionId, onClose }) => {
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);
	const [sessionData, setSessionData] = useState(null);

	useEffect(() => {
		const fetchSession = async () => {
			setLoading(true);
			setError(null);

			try {
				const response = await apiFetch({
					path: `/generation-session/${sessionId}`,
				});
				setSessionData(response);
			} catch (err) {
				console.error('Failed to fetch session:', err);
				setError(__('Failed to load session data.', 'ai-post-scheduler'));
			} finally {
				setLoading(false);
			}
		};

		if (sessionId) {
			fetchSession();
		}
	}, [sessionId]);

	const renderAICalls = () => {
		if (!sessionData || !sessionData.ai_calls || sessionData.ai_calls.length === 0) {
			return <p>{__('No AI calls recorded.', 'ai-post-scheduler')}</p>;
		}

		return (
			<div className="aips-ai-calls">
				{sessionData.ai_calls.map((call, index) => (
					<div key={index} className="aips-ai-call">
						<h4>{call.label}</h4>

						{call.request && (
							<div className="aips-ai-request">
								<h5>{__('Request', 'ai-post-scheduler')}</h5>
								<div className="aips-code-block">
									<strong>{__('Prompt:', 'ai-post-scheduler')}</strong>
									<pre>{call.request.prompt || __('No prompt recorded', 'ai-post-scheduler')}</pre>
								</div>
								{call.request.model && (
									<p>
										<strong>{__('Model:', 'ai-post-scheduler')}</strong> {call.request.model}
									</p>
								)}
							</div>
						)}

						{call.response && (
							<div className="aips-ai-response">
								<h5>{__('Response', 'ai-post-scheduler')}</h5>
								<div className="aips-code-block">
									<pre>{call.response.output || __('No output recorded', 'ai-post-scheduler')}</pre>
								</div>
								{call.response.tokens && (
									<p>
										<strong>{__('Tokens:', 'ai-post-scheduler')}</strong> {call.response.tokens}
									</p>
								)}
							</div>
						)}
					</div>
				))}
			</div>
		);
	};

	const renderLogs = () => {
		if (!sessionData || !sessionData.logs || sessionData.logs.length === 0) {
			return <p>{__('No logs recorded.', 'ai-post-scheduler')}</p>;
		}

		return (
			<div className="aips-logs">
				<table className="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th>{__('Type', 'ai-post-scheduler')}</th>
							<th>{__('Time', 'ai-post-scheduler')}</th>
							<th>{__('Message', 'ai-post-scheduler')}</th>
						</tr>
					</thead>
					<tbody>
						{sessionData.logs.map((log, index) => (
							<tr key={index} className={`aips-log-${log.type.toLowerCase()}`}>
								<td>
									<span className={`aips-log-badge aips-log-${log.type.toLowerCase()}`}>
										{log.type}
									</span>
								</td>
								<td>{new Date(log.timestamp).toLocaleString()}</td>
								<td>
									{log.details && log.details.message ? (
										<div>
											<div>{log.details.message}</div>
											{log.details.context && (
												<details className="aips-log-details">
													<summary>{__('Context', 'ai-post-scheduler')}</summary>
													<pre>{JSON.stringify(log.details.context, null, 2)}</pre>
												</details>
											)}
										</div>
									) : (
										<pre>{JSON.stringify(log.details, null, 2)}</pre>
									)}
								</td>
							</tr>
						))}
					</tbody>
				</table>
			</div>
		);
	};

	const renderSessionInfo = () => {
		if (!sessionData || !sessionData.history) {
			return null;
		}

		const { history } = sessionData;

		return (
			<div className="aips-session-info">
				<div className="aips-session-detail">
					<strong>{__('Session ID:', 'ai-post-scheduler')}</strong> {history.id}
				</div>
				<div className="aips-session-detail">
					<strong>{__('Status:', 'ai-post-scheduler')}</strong> {history.status}
				</div>
				<div className="aips-session-detail">
					<strong>{__('Created:', 'ai-post-scheduler')}</strong> {new Date(history.created_at).toLocaleString()}
				</div>
				{history.completed_at && (
					<div className="aips-session-detail">
						<strong>{__('Completed:', 'ai-post-scheduler')}</strong> {new Date(history.completed_at).toLocaleString()}
					</div>
				)}
				{history.generated_title && (
					<div className="aips-session-detail">
						<strong>{__('Generated Title:', 'ai-post-scheduler')}</strong> {history.generated_title}
					</div>
				)}
			</div>
		);
	};

	return (
		<Modal
			title={__('Generation Session Details', 'ai-post-scheduler')}
			onRequestClose={onClose}
			className="aips-session-modal"
			size="large"
		>
			{loading && (
				<div className="aips-loading-container">
					<Spinner />
					<p>{__('Loading session data...', 'ai-post-scheduler')}</p>
				</div>
			)}

			{error && (
				<Notice status="error" isDismissible={false}>
					{error}
				</Notice>
			)}

			{!loading && !error && sessionData && (
				<div className="aips-session-content">
					{renderSessionInfo()}

					<TabPanel
						className="aips-session-tabs"
						activeClass="active-tab"
						tabs={[
							{
								name: 'ai-calls',
								title: __('AI Calls', 'ai-post-scheduler'),
								className: 'tab-ai-calls',
							},
							{
								name: 'logs',
								title: __('Logs', 'ai-post-scheduler'),
								className: 'tab-logs',
							},
						]}
					>
						{(tab) => (
							<div className="aips-tab-content">
								{tab.name === 'ai-calls' && renderAICalls()}
								{tab.name === 'logs' && renderLogs()}
							</div>
						)}
					</TabPanel>
				</div>
			)}
		</Modal>
	);
};

export default SessionModal;
