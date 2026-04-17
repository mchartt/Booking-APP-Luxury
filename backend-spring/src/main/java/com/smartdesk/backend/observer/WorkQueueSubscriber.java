package com.smartdesk.backend.observer;

import com.smartdesk.backend.model.Desk;

public class WorkQueueSubscriber implements Subscriber {

    private final Long waitingWorkerId;
    private boolean notified;

    public WorkQueueSubscriber(Long waitingWorkerId) {
        this.waitingWorkerId = waitingWorkerId;
    }

    @Override
    public void update(Desk context) {
        if ("AVAILABLE".equals(context.getStateName())) {
            notified = true;
        }
    }

    public Long getWaitingWorkerId() {
        return waitingWorkerId;
    }

    public boolean isNotified() {
        return notified;
    }
}
