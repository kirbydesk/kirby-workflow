// Panel side of kirby-workflow: the view „Workflow“ with the tabs Changes, History and GitHub.
// The confirmation is a Kirby text dialog built on the server (index.php).
panel.plugin("kirbydesk/workflow", {
	icons: {
		// The KirbyDesk mark (menu bar icon of the app) – 19 of 24 pt, like the margin of Kirby's own icons
		kirbydesk: '<g transform="translate(2.5 2.5) scale(0.019)"><path d="M952.6344646,676.0140395c-26.4108123-56.3598909-62.6777624-115.9610355-106.7027047-176.0135203,44.0245714-60.0522623,80.2915215-119.6542228,106.7027047-176.0157451,27.3847607-58.4413504,43.0604864-111.0513214,46.5917073-156.3692499,4.2479069-54.565432-8.9087388-97.5434521-39.1032882-127.7385947C933.662458,13.4165037,897.2099586,0,851.7798996,0c-6.266256,0-12.7923699.2604513-19.3956107.7748277-45.3149621,3.5288478-97.9261196,19.2039802-156.3704365,46.591114-114.9723293,53.8760371-243.4246436,148.682081-361.6950335,266.9530642-37.8477529,37.8473079-73.2766906,76.7385716-105.7459615,115.8955519-34.3580618-48.7855199-62.8633117-96.8055562-84.1912454-142.319194-22.6978243-48.4380043-36.118481-92.3142549-38.8113929-126.8836738-2.2313377-28.6543865,2.6294306-49.1754552,14.4470364-60.993061,9.9386783-9.9374917,26.1738699-14.9762449,48.2546798-14.9762449,14.5051782,0,31.1046456,2.1909944,49.3380147,6.5118752,56.9029674,13.484138,139.2815105,51.8909119,209.8685522,97.8448399l1.6972346,1.1042481,69.6692328-58.8673277-10.8836299-7.0857282C389.1205438,73.2229985,283.6698147,24.5405616,217.2227024,8.7930486,192.7835922,3.0014191,169.7481898.0652611,148.7554611.0652611,91.6697625.0652611,58.0377309,21.7147542,39.8767424,39.8769294,9.6798199,70.0732586-3.4756393,113.0506854.7746407,167.6155241c3.5288478,45.3155554,19.2039802,97.9255264,46.591114,156.3698432,26.4101449,56.3603358,62.6768725,115.9619255,106.7018889,176.0144844-44.0250164,60.0523365-80.2916698,119.6537036-106.7018889,176.0141878-27.3871338,58.4443168-43.0622662,111.0554743-46.591114,156.3692499-4.25028,54.565432,8.9051791,97.5428588,39.1021016,127.7397813,18.1621751,18.1627684,51.790647,39.8128548,108.8549874,39.8128548,22.4842424,0,47.2905948-3.3674748,73.729069-10.0086857,64.3468902-16.1610313,151.9463245-59.3188161,240.3360118-118.4086249l11.0722198-7.4011323-67.6874448-56.2972995c-75.6625786,50.5821294-146.3564113,85.0281453-204.4388254,99.6169764-19.926599,5.0049361-37.9374869,7.5430012-53.5319329,7.5430012-22.0262279,0-38.2406546-5.0464659-48.1929784-14.999383-11.8181991-11.8181991-16.6789674-32.3386745-14.4476297-60.9918744,2.6929119-34.5694189,16.1135686-78.4450762,38.8113929-126.8842671,21.3279337-45.5135636,49.8331095-93.5336,84.1910971-142.319194,32.4693451,39.1574253,67.8984311,78.0490598,105.7461098,115.8967385,118.2697967,118.2697967,246.7227042,213.0752473,361.6950335,266.9524709,58.4431303,27.3859473,111.0542878,43.061673,156.3680633,46.5893342,6.6008676.5149697,13.125795.7760143,19.3932375.7760143,45.4336187,0,81.8861181-13.4165037,108.3465438-39.8769294,30.195736-30.195736,43.3523817-73.1731627,39.1044748-127.7397813-3.5312209-45.3161487-19.2069466-97.9261196-46.5917073-156.3692499ZM899.9805907,899.9807776c-9.9303723,9.9303723-26.161411,14.9661591-48.2422209,14.9661591-4.0497503,0-8.3391871-.1744252-12.7496535-.5185294-34.5706054-2.6923186-78.4462628-16.1123821-126.8842671-38.8113929-106.2629336-49.7942494-226.1737513-138.6063543-337.6445238-250.0771268-41.0689092-41.0691317-79.0484451-83.2841897-113.1481321-125.5400359,34.099687-42.2558462,72.0792229-84.4708301,113.1481321-125.5397393,111.4701792-111.4701792,231.3815902-200.2828774,337.6433372-250.0777201,48.4403774-22.6984176,92.3160348-36.1190743,126.8854536-38.8119862,4.411653-.3435109,8.7010897-.5179361,12.7508401-.5179361,22.0796233,0,38.310662,5.0351936,48.2422209,14.9655658,11.8181991,11.8181991,16.6783741,32.3392677,14.4464431,60.9936542-2.6911321,34.5682323-16.1123821,78.4438897-38.8113929,126.8836738-21.3271921,45.5133412-49.8320712,93.5335258-84.1900588,142.3194907-32.4689743-39.1572028-67.8976154-78.0487631-105.744775-115.8964418-32.7717711-32.7711778-63.0351413-60.831688-95.2385473-88.3084075l-.5250555-.4473354-68.1367084,53.6389463,6.9460842,5.9260823c32.8524576,28.0290661,67.234399,59.7548792,96.811934,89.3324141,41.0692059,41.0692059,79.0487418,83.2841897,113.1483546,125.5400359-34.0993162,42.2555496-72.0784071,84.4705334-113.147168,125.5397393-29.7484005,29.7460274-65.664572,60.4721584-97.3518216,87.5816358l-6.9147144,5.915329,68.6553862,53.1678795c31.4321379-26.8903334,63.9284032-54.6998848,95.7522563-86.5237378,37.8471596-37.8467888,73.2758749-76.7379041,105.7450716-115.8951811,34.357691,48.7854457,62.8623476,96.8052596,84.1897622,142.3188232,22.6990109,48.4391909,36.1202609,92.3148482,38.8113929,126.8842671,2.231931,28.6531999-2.6294306,49.1736753-14.4476297,60.9918744Z"/><path d="M589.9306273,772.2053994l-.0008899-.0006674c-.0560653.0479818-.1125013.0962602-.1685666.144242l.1694565-.1435745Z"/></g>',
		// Remix Icon „upload-cloud-2-line“ (Apache 2.0), like Kirby's own icons
		"cloud-upload": '<path d="M12 12.586l4.243 4.242-1.415 1.415L13 16.415V22h-2v-5.587l-1.828 1.83-1.415-1.415L12 12.586zM12 2a7.001 7.001 0 0 1 6.954 6.194 5.5 5.5 0 0 1-.953 10.784v-2.014a3.5 3.5 0 1 0-1.112-6.91 5 5 0 1 0-9.777 0 3.5 3.5 0 0 0-1.292 6.88l.18.03v2.014a5.5 5.5 0 0 1-.954-10.784A7 7 0 0 1 12 2z"/>'
	},
	components: {
		"k-workflow-view": {
			props: {
				// GitHub's answer when there is no connection – then only this message, no tabs, no button
				connection: String,
				tab: String,
				changes: Object,
				history: Object,
				github: Object
			},
			data() {
				return { running: false, timer: null };
			},
			computed: {
				tabs() {
					const link = (name) => "/workflow?tab=" + name;
					const tabs = [];
					if (this.changes) {
						const count = this.changes.data?.length;
						tabs.push({ name: "changes", label: this.$t("kirbydesk.workflow.changes"), link: link("changes"), badge: count || null });
					}
					tabs.push({ name: "history", label: this.$t("kirbydesk.workflow.history"), link: link("history") });
					tabs.push({ name: "github", label: "GitHub", link: link("github") });
					return tabs;
				}
			},
			async created() {
				this.$panel.events.on("workflow.started", this.started);
				if (!this.connection) await this.poll();
			},
			destroyed() {
				this.$panel.events.off("workflow.started", this.started);
				clearTimeout(this.timer);
			},
			methods: {
				// Only while publishing runs: is it done? Then say so and show the new state
				async poll() {
					let status;
					try {
						status = await this.$api.get("workflow/status");
					} catch (e) {
						return;
					}
					const wasRunning = this.running;
					this.running = Boolean(status.running);
					if (wasRunning && !this.running) {
						if (status.failed) {
							const reason = status.failed.message;
							this.$panel.notification.error(reason
								? this.$t("kirbydesk.workflow.error", { reason })
								: this.$t("kirbydesk.workflow.error.unknown"));
						} else {
							this.$panel.notification.success(this.$t("kirbydesk.workflow.done"));
						}
						this.$panel.view.reload();
					}
					clearTimeout(this.timer);
					if (this.running) this.timer = setTimeout(this.poll, 5000);
				},
				started() {
					this.running = true;
					clearTimeout(this.timer);
					this.timer = setTimeout(this.poll, 3000);
				},
				publish() {
					this.$panel.dialog.open("workflow/publish");
				},
				statusTheme(status) {
					return { changed: "notice", new: "positive", deleted: "negative" }[status];
				}
			},
			template: `
				<k-panel-inside class="k-workflow-view">
					<k-header>
						{{ $t("kirbydesk.workflow") }}
						<template v-if="!connection" #buttons>
							<k-button
								:disabled="running"
								:icon="running ? 'loader' : 'cloud-upload'"
								:text="running ? $t('kirbydesk.workflow.running') : $t('kirbydesk.workflow.publish')"
								variant="filled"
								theme="positive"
								size="sm"
								@click="publish"
							/>
						</template>
					</k-header>
					<k-box v-if="connection" theme="negative" icon="alert" class="k-workflow-connection">
						<strong>{{ $t("kirbydesk.workflow.connection") }}</strong>
						<span>{{ connection }}</span>
						<span>{{ $t("kirbydesk.workflow.connection.hint") }}</span>
					</k-box>
					<k-tabs v-else :tab="tab" :tabs="tabs" theme="notice" />

					<template v-if="connection" />
					<template v-else-if="tab === 'changes' && changes">
						<k-box v-if="changes.error" theme="negative" :text="changes.error" />
						<k-empty v-else-if="changes.data.length === 0" icon="check">{{ $t("kirbydesk.workflow.changes.none") }}</k-empty>
						<ul v-else class="k-workflow-list">
							<li v-for="item in changes.data" :key="item.type + item.path + item.title">
								<div class="k-workflow-main">
									<strong>{{ item.title }}</strong>
									<a v-if="item.url" :href="item.url" target="_blank">{{ item.path }}</a>
									<span v-else>{{ item.path }}</span>
								</div>
								<k-bubble :text="$t('kirbydesk.workflow.status.' + item.status)" :theme="statusTheme(item.status)" />
							</li>
						</ul>
					</template>

					<template v-else-if="tab === 'history' && history">
						<k-box v-if="history.error" theme="negative" :text="history.error" />
						<k-empty v-else-if="history.data.length === 0" icon="history">{{ $t("kirbydesk.workflow.history.none") }}</k-empty>
						<ul v-else class="k-workflow-list">
							<li v-for="(entry, index) in history.data" :key="index">
								<div class="k-workflow-main">
									<strong>{{ entry.date }}</strong>
									<span>{{ entry.by }}</span>
								</div>
								<span v-if="entry.failed" class="k-workflow-failed">
									{{ entry.reason ? $t("kirbydesk.workflow.failed.reason", { reason: entry.reason }) : $t("kirbydesk.workflow.failed") }}
								</span>
							</li>
						</ul>
					</template>

					<template v-else-if="tab === 'github' && github">
						<k-box v-if="github.error" theme="negative" :text="github.error" />
						<template v-else>
							<template v-if="github.data.content">
								<h2 class="k-workflow-heading">{{ $t("kirbydesk.workflow.content") }}</h2>
								<ul class="k-workflow-list">
									<li><strong>{{ $t("kirbydesk.workflow.content.repository") }}</strong><a :href="'https://github.com/' + github.data.content.repository" target="_blank">{{ github.data.content.repository }}</a></li>
									<li><strong>{{ $t("kirbydesk.workflow.compare") }}</strong><span :class="{ 'k-workflow-failed': !github.data.content.compare }">{{ github.data.content.compare ? $t("kirbydesk.workflow.allowed") : $t("kirbydesk.workflow.missing") }}</span></li>
									<li><strong>{{ $t("kirbydesk.workflow.start") }}</strong><span :class="{ 'k-workflow-failed': !github.data.publish }">{{ github.data.publish ? $t("kirbydesk.workflow.allowed") : $t("kirbydesk.workflow.missing") }}</span></li>
								</ul>
							</template>
							<h2 class="k-workflow-heading">{{ $t("kirbydesk.workflow.code") }}</h2>
							<ul class="k-workflow-list">
								<li><strong>{{ $t("kirbydesk.workflow.code.repository") }}</strong><a :href="'https://github.com/' + github.data.code.repository" target="_blank">{{ github.data.code.repository }}</a></li>
								<li v-if="github.data.code.last">
									<strong>{{ $t("kirbydesk.workflow.last") }}</strong>
									<span :class="{ 'k-workflow-failed': github.data.code.last.failed }">
										{{ github.data.code.last.failed ? $t("kirbydesk.workflow.failed") + " · " : "" }}{{ github.data.code.last.date }}{{ github.data.code.last.by ? " · " + github.data.code.last.by : "" }}
									</span>
								</li>
							</ul>
						</template>
					</template>
				</k-panel-inside>
			`
		}
	}
});
