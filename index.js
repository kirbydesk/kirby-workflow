// Panel side of kirby-workflow: the view „Workflow“ with the section „Publish“.
// The confirmation is a Kirby text dialog built on the server (index.php).
panel.plugin("kirbydesk/workflow", {
	components: {
		"k-workflow-view": {
			data() {
				return { status: null, error: null, timer: null };
			},
			computed: {
				running() {
					return this.status?.running ?? null;
				},
				lastPublished() {
					const published = this.status?.published;
					if (!published) return this.$t("kirbydesk.workflow.never");
					if (!published.by) return published.date;
					return this.$t("kirbydesk.workflow.last.by", { date: published.date, name: published.by });
				},
				state() {
					if (this.running) return this.$t("kirbydesk.workflow.running.since", { time: this.running.time });
					if (this.status?.failed) {
						const reason = this.status.failed.message;
						return this.$t("kirbydesk.workflow.failed") + (reason ? " – " + reason : "");
					}
					return this.$t("kirbydesk.workflow.ready");
				}
			},
			async created() {
				this.$panel.events.on("workflow.started", this.started);
				await this.load();
			},
			destroyed() {
				this.$panel.events.off("workflow.started", this.started);
				clearTimeout(this.timer);
			},
			methods: {
				async load() {
					const wasRunning = Boolean(this.running);
					try {
						this.status = await this.$api.get("workflow/status");
						this.error = null;
					} catch (e) {
						this.error = e.message;
					}
					// Finished while the page was open: say so
					if (wasRunning && !this.running && this.status) {
						if (this.status.failed) {
							const reason = this.status.failed.message;
							this.$panel.notification.error(reason
								? this.$t("kirbydesk.workflow.error", { reason })
								: this.$t("kirbydesk.workflow.error.unknown"));
						} else {
							this.$panel.notification.success(this.$t("kirbydesk.workflow.done"));
						}
					}
					clearTimeout(this.timer);
					if (this.running) this.timer = setTimeout(this.load, 5000);
				},
				started() {
					this.status = { ...(this.status ?? {}), running: { time: new Date().toTimeString().slice(0, 5) }, failed: null };
					clearTimeout(this.timer);
					this.timer = setTimeout(this.load, 3000);
				},
				publish() {
					this.$panel.dialog.open("workflow/publish");
				}
			},
			template: `
				<k-panel-inside class="k-workflow-view">
					<k-header>{{ $t("kirbydesk.workflow") }}</k-header>
					<k-section :label="$t('kirbydesk.workflow.publish')">
						<div class="k-workflow-box">
							<p class="k-workflow-intro">{{ $t("kirbydesk.workflow.publish.intro") }}</p>
							<dl class="k-workflow-rows">
								<div><dt>{{ $t("kirbydesk.workflow.last") }}</dt><dd>{{ status ? lastPublished : "…" }}</dd></div>
								<div><dt>{{ $t("kirbydesk.workflow.status") }}</dt><dd>{{ error ?? (status ? state : "…") }}</dd></div>
							</dl>
							<k-button
								:disabled="!status || Boolean(running)"
								:icon="running ? 'loader' : 'cloud-upload'"
								:text="running ? $t('kirbydesk.workflow.running') : $t('kirbydesk.workflow.publish')"
								variant="filled"
								theme="positive"
								size="lg"
								@click="publish"
							/>
						</div>
					</k-section>
				</k-panel-inside>
			`
		}
	}
});
