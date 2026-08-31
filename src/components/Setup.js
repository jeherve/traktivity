/**
 * Internal dependencies
 */
import Header from './Header';
import Intro from './Intro';
import TraktForm from './TraktForm';
import TmdbForm from './TmdbForm';
import SyncForm from './SyncForm';
import Dashboard from './Dashboard';
import Notice from './Notice';
import useTraktivitySettings from '../hooks/use-traktivity-settings';

/**
 * The dashboard shell: renders the current step of the setup wizard.
 *
 * @return {Element} The app.
 */
export default function Setup() {
	const {
		step,
		trakt,
		tmdb,
		sync,
		notice,
		removeNotice,
		goToNextStep,
		saveTraktCredentials,
		saveTmdbCredentials,
		launchSync,
	} = useTraktivitySettings();

	// Steps 2 and 3 render the notice inside their own form, next to the
	// fields it refers to.
	const ownsNotice = step === 2 || step === 3;

	const renderStep = () => {
		switch ( step ) {
			case 1:
				return <Intro nextStep={ goToNextStep } />;
			case 2:
				return (
					<TraktForm
						trakt={ trakt }
						saveCreds={ saveTraktCredentials }
						nextStep={ goToNextStep }
						notice={ notice }
						removeNotice={ removeNotice }
					/>
				);
			case 3:
				return (
					<TmdbForm
						tmdb={ tmdb }
						saveCreds={ saveTmdbCredentials }
						nextStep={ goToNextStep }
						notice={ notice }
						removeNotice={ removeNotice }
					/>
				);
			case 4:
				return <SyncForm nextStep={ goToNextStep } />;
			default:
				return <Dashboard sync={ sync } launchSync={ launchSync } />;
		}
	};

	return (
		<div
			className={ `traktivity-dashboard traktivity-dashboard--step-${ step }` }
		>
			<Header />
			<div className="traktivity-dashboard__body">
				{ ! ownsNotice && (
					<Notice notice={ notice } removeNotice={ removeNotice } />
				) }
				{ renderStep() }
			</div>
		</div>
	);
}
